<?php

namespace App\Actions\Maintenance;

use App\Enums\Priority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\ChecklistTemplate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Crea un ordine di lavoro (a mano o accettando una segnalazione).
 * Con degli assegnatari parte già "pianificato".
 */
class CreateWorkOrder
{
    public function __construct(private AssignWorkOrder $assign) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(WorkOrderType::class)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'due_at' => ['nullable', 'date'],
            'planned_start_at' => ['nullable', 'date'],
            'assignees' => ['array'],
            'assignees.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
            'checklist_template_id' => ['nullable', 'integer', 'exists:checklist_templates,id'],
        ];
    }

    /**
     * $actor è null quando l'ordine lo crea il sistema (piani preventivi).
     * Il collegamento al piano non arriva mai dai dati del modulo.
     *
     * @param  array<string, mixed>  $input
     * @param  array{maintenance_plan_id?: int, plan_due_on?: string|null}  $plan
     */
    public function handle(?User $actor, array $input, array $plan = []): WorkOrder
    {
        $data = Validator::make($input, self::rules(), [], [
            'title' => __('maintenance.field.title'),
            'type' => __('maintenance.field.type'),
            'priority' => __('maintenance.field.priority'),
            'due_at' => __('maintenance.field.due_at'),
        ])->validate();

        $order = DB::transaction(function () use ($actor, $data, $plan) {
            $order = new WorkOrder([
                'type' => $data['type'],
                'title' => trim((string) $data['title']),
                'description' => isset($data['description']) ? trim((string) $data['description']) ?: null : null,
                'asset_id' => $data['asset_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'priority' => $data['priority'],
                'due_at' => $data['due_at'] ?? null,
                'planned_start_at' => $data['planned_start_at'] ?? null,
            ]);
            $order->created_by = $actor?->id;
            $order->forceFill(['maintenance_plan_id' => $plan['maintenance_plan_id'] ?? null, 'plan_due_on' => $plan['plan_due_on'] ?? null]);
            $order->save();
            $order->statusChanges()->create(['from' => null, 'to' => WorkOrderStatus::Open, 'user_id' => $actor?->id]);

            if (! empty($data['checklist_template_id'])) {
                $this->copyChecklist($order, (int) $data['checklist_template_id']);
            }

            return $order;
        });

        Audit::log('work_orders.created', $order, $order->number.' · '.$order->title, actor: $actor);

        if (! empty($data['assignees'])) {
            $this->assign->handle($order, array_map('intval', $data['assignees']), $actor);
        }

        return $order->refresh();
    }

    public function copyChecklist(WorkOrder $order, int $templateId): void
    {
        $template = ChecklistTemplate::query()->with('items')->findOrFail($templateId);
        $start = (int) $order->checklist()->max('position');
        foreach ($template->items as $i => $item) {
            $order->checklist()->create([
                'position' => $start + $i + 1,
                'label' => $item->label,
                'kind' => $item->kind,
                'unit' => $item->unit,
                'required' => $item->required,
            ]);
        }
    }
}
