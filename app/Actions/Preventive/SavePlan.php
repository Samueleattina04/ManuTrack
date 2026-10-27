<?php

namespace App\Actions\Preventive;

use App\Enums\IntervalUnit;
use App\Enums\PlanMode;
use App\Enums\PlanTrigger;
use App\Enums\Priority;
use App\Models\MaintenancePlan;
use App\Models\Meter;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Numbers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crea o modifica un piano preventivo.
 * La "prossima scadenza" si può sempre correggere a mano: per i piani a date
 * fisse diventa il nuovo punto di partenza del calendario.
 */
class SavePlan
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'trigger' => ['required', Rule::enum(PlanTrigger::class)],
            'schedule_mode' => ['required', Rule::enum(PlanMode::class)],
            'interval_value' => ['required_if:trigger,tempo', 'nullable', 'integer', 'min:1', 'max:999'],
            'interval_unit' => ['required_if:trigger,tempo', 'nullable', Rule::enum(IntervalUnit::class)],
            'next_due_on' => ['required_if:trigger,tempo', 'nullable', 'date', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
            'lead_days' => ['required', 'integer', 'min:0', 'max:365'],
            'meter_id' => ['required_if:trigger,contatore', 'nullable', 'integer', 'exists:meters,id'],
            'meter_interval' => ['required_if:trigger,contatore', 'nullable', 'numeric', 'gt:0', 'max:100000000'],
            'meter_lead' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'next_meter_due' => ['required_if:trigger,contatore', 'nullable', 'numeric', 'min:0', 'max:1000000000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'checklist_template_id' => ['nullable', 'integer', 'exists:checklist_templates,id'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return collect(array_keys(self::rules()))->mapWithKeys(fn ($k) => [$k => __('preventive.field.'.$k)])->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?MaintenancePlan $plan, User $actor): MaintenancePlan
    {
        foreach (['meter_interval', 'meter_lead', 'next_meter_due'] as $k) {
            if (isset($input[$k]) && is_string($input[$k]) && trim($input[$k]) !== '') {
                $input[$k] = Numbers::parse($input[$k]) ?? $input[$k];
            }
        }
        $input = array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v, $input);
        $data = Validator::make($input, self::rules(), [], self::attributes())->validate();

        // Il contatore deve essere della stessa macchina; l'assegnatario deve poter eseguire gli ordini
        if (($data['trigger'] ?? null) === PlanTrigger::Meter->value
            && ! Meter::query()->whereKey($data['meter_id'])->where('asset_id', $data['asset_id'])->exists()) {
            throw ValidationException::withMessages(['meter_id' => __('preventive.errors.meter_other_asset')]);
        }
        if (! empty($data['assignee_id']) && ! User::query()->findOrFail($data['assignee_id'])->can('work_orders.execute')) {
            throw ValidationException::withMessages(['assignee_id' => __('preventive.errors.assignee')]);
        }

        $isTime = $data['trigger'] === PlanTrigger::Time->value;
        $attributes = [
            'title' => trim((string) $data['title']),
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'asset_id' => (int) $data['asset_id'],
            'trigger' => $data['trigger'],
            'schedule_mode' => $data['schedule_mode'],
            'interval_value' => $isTime ? (int) $data['interval_value'] : null,
            'interval_unit' => $isTime ? $data['interval_unit'] : null,
            'lead_days' => (int) $data['lead_days'],
            'meter_id' => $isTime ? null : (int) $data['meter_id'],
            'meter_interval' => $isTime ? null : $data['meter_interval'],
            'meter_lead' => $isTime ? 0 : ($data['meter_lead'] ?? 0),
            'priority' => $data['priority'],
            'assignee_id' => $data['assignee_id'] ?? null,
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
            'checklist_template_id' => $data['checklist_template_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        $creating = $plan === null;
        $plan ??= new MaintenancePlan;
        $plan->fill($attributes);
        if ($isTime) {
            $due = (string) $data['next_due_on'];
            if ($creating || $plan->next_due_on?->toDateString() !== $due || $plan->isDirty(['interval_value', 'interval_unit', 'trigger'])) {
                // Nuovo punto di partenza del calendario
                $plan->forceFill(['start_on' => $due]);
            }
            $plan->forceFill(['next_due_on' => $due, 'next_meter_due' => null]);
        } else {
            $plan->forceFill(['next_meter_due' => (string) $data['next_meter_due'], 'next_due_on' => null, 'start_on' => null]);
        }
        if ($creating) {
            $plan->created_by = $actor->id;
        }
        $plan->save();

        Audit::log($creating ? 'preventive.plan_created' : 'preventive.plan_updated', $plan, $plan->title.' · '.$plan->asset->code, $creating ? [] : Audit::diff($plan), $actor);

        return $plan;
    }
}
