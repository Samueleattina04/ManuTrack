<?php

namespace App\Livewire\Preventive;

use App\Actions\Preventive\SavePlan;
use App\Enums\IntervalUnit;
use App\Enums\PlanMode;
use App\Enums\Priority;
use App\Models\Asset;
use App\Models\ChecklistTemplate;
use App\Models\MaintenancePlan;
use App\Models\Meter;
use App\Models\Part;
use App\Models\User;
use App\Support\Numbers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Nuovo piano preventivo o modifica.
 */
class Form extends Component
{
    public ?MaintenancePlan $plan = null;

    #[Url(as: 'macchina')]
    public string $assetParam = '';

    /** @var array<string, mixed> */
    public array $form = [
        'title' => '', 'description' => '', 'asset_id' => '', 'trigger' => 'tempo', 'schedule_mode' => 'fisso',
        'interval_value' => '1', 'interval_unit' => 'mesi', 'next_due_on' => '', 'lead_days' => '7',
        'meter_id' => '', 'meter_interval' => '', 'meter_lead' => '', 'next_meter_due' => '',
        'priority' => 'media', 'assignee_id' => '', 'estimated_minutes' => '', 'checklist_template_id' => '', 'is_active' => true,
    ];

    public function mount(?MaintenancePlan $plan = null): void
    {
        $this->authorize('preventive.manage');
        if ($plan?->exists) {
            $this->plan = $plan;
            $this->form = [
                'title' => $plan->title, 'description' => (string) $plan->description, 'asset_id' => (string) $plan->asset_id,
                'trigger' => $plan->trigger->value, 'schedule_mode' => $plan->schedule_mode->value,
                'interval_value' => (string) ($plan->interval_value ?? '1'), 'interval_unit' => $plan->interval_unit->value ?? 'mesi',
                'next_due_on' => $plan->next_due_on?->toDateString() ?? '', 'lead_days' => (string) $plan->lead_days,
                'meter_id' => (string) ($plan->meter_id ?? ''), 'meter_interval' => $plan->meter_interval !== null ? Part::formatQty($plan->meter_interval) : '',
                'meter_lead' => (float) $plan->meter_lead > 0 ? Part::formatQty($plan->meter_lead) : '',
                'next_meter_due' => $plan->next_meter_due !== null ? Part::formatQty($plan->next_meter_due) : '',
                'priority' => $plan->priority->value, 'assignee_id' => (string) ($plan->assignee_id ?? ''),
                'estimated_minutes' => (string) ($plan->estimated_minutes ?? ''), 'checklist_template_id' => (string) ($plan->checklist_template_id ?? ''),
                'is_active' => $plan->is_active,
            ];
        } else {
            $this->form['next_due_on'] = today()->addDays(14)->toDateString();
            if (ctype_digit($this->assetParam) && Asset::query()->whereKey((int) $this->assetParam)->exists()) {
                $this->form['asset_id'] = $this->assetParam;
            }
        }
    }

    /** Cambiando macchina il contatore scelto non vale più */
    public function updatedFormAssetId(): void
    {
        $this->form['meter_id'] = '';
    }

    /** Scelto il contatore, si propone la prossima soglia: valore attuale + intervallo */
    public function updatedFormMeterId(): void
    {
        $this->suggestThreshold();
    }

    public function updatedFormMeterInterval(): void
    {
        $this->suggestThreshold();
    }

    private function suggestThreshold(): void
    {
        $meter = Meter::query()->find((int) $this->form['meter_id']);
        $interval = Numbers::parse($this->form['meter_interval']);
        if ($meter && $interval && $this->form['next_meter_due'] === '') {
            $this->form['next_meter_due'] = Part::formatQty((float) $meter->current_value + $interval);
        }
    }

    public function save(SavePlan $save): RedirectResponse|Redirector|null
    {
        $this->authorize('preventive.manage');
        $this->resetErrorBag();

        try {
            $plan = $save->handle($this->form, $this->plan, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('form.'.$field, $messages[0]);
            }

            return null;
        }

        session()->flash('status', __($this->plan ? 'preventive.plan.saved' : 'preventive.plan.created'));

        return redirect()->route('preventive.show', $plan);
    }

    public function render(): View
    {
        $title = $this->plan ? __('preventive.plan.edit') : __('preventive.plan.new');
        $assetId = ctype_digit((string) $this->form['asset_id']) ? (int) $this->form['asset_id'] : 0;

        return view('livewire.preventive.form', [
            'assets' => Asset::query()->orderBy('code')->get(['id', 'code', 'name']),
            'meters' => $assetId ? Meter::query()->where('asset_id', $assetId)->orderBy('name')->get() : collect(),
            'people' => User::permission('work_orders.execute')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_title']),
            'templates' => ChecklistTemplate::query()->orderBy('name')->pluck('name', 'id'),
            'units' => IntervalUnit::cases(),
            'modes' => PlanMode::cases(),
            'priorities' => Priority::cases(),
            'title' => $title,
        ])->title($title)->layoutData(['heading' => $title]);
    }
}
