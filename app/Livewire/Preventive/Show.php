<?php

namespace App\Livewire\Preventive;

use App\Actions\Preventive\GeneratePlanOrders;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Support\Audit\Audit;
use App\Support\Numbers;
use App\Support\Preventive\PlanCompliance;
use App\Support\Preventive\PlanSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Scheda del piano: prossime scadenze, ordine aperto, interventi, ricambi previsti.
 */
class Show extends Component
{
    public MaintenancePlan $plan;

    public string $partToAdd = '';

    public string $partQty = '1';

    public string $copyTo = '';

    public function mount(MaintenancePlan $plan): void
    {
        $this->authorize('preventive.view');
        $this->plan = $plan;
    }

    public function generate(GeneratePlanOrders $generator): RedirectResponse|Redirector|null
    {
        $this->authorize('preventive.manage');
        $this->authorize('work_orders.create');
        $order = $generator->generate($this->plan, auth()->user(), force: true);
        if (! $order) {
            $this->dispatch('toast', message: __('preventive.plan.not_generated'));

            return null;
        }
        session()->flash('status', __('preventive.plan.generated', ['number' => $order->number]));

        return redirect()->route('work-orders.show', $order);
    }

    public function addPart(): void
    {
        $this->authorize('preventive.manage');
        $this->resetErrorBag();
        $qty = Numbers::parse($this->partQty);
        $part = Part::query()->where('is_active', true)->find((int) $this->partToAdd);
        if (! $part || $qty === null || $qty <= 0 || $qty > 100000) {
            $this->addError('partToAdd', __('inventory.errors.quantity'));

            return;
        }
        $this->plan->parts()->syncWithoutDetaching([$part->id => ['quantity' => $qty]]);
        $this->reset('partToAdd');
        $this->partQty = '1';
    }

    public function removePart(int $partId): void
    {
        $this->authorize('preventive.manage');
        $this->plan->parts()->detach($partId);
    }

    /** Copia il piano su un'altra macchina (stessa frequenza, ricambi e checklist; senza contatore) */
    public function duplicate(): RedirectResponse|Redirector|null
    {
        $this->authorize('preventive.manage');
        $asset = Asset::query()->find((int) $this->copyTo);
        if (! $asset || $asset->id === $this->plan->asset_id) {
            $this->addError('copyTo', __('validation.required', ['attribute' => __('preventive.plan.duplicate_choose')]));

            return null;
        }

        $copy = $this->plan->replicate(['current_work_order_id', 'last_generated_at', 'last_done_at', 'meter_id', 'next_meter_due']);
        $copy->asset_id = $asset->id;
        $copy->created_by = auth()->id();
        if (! $copy->isTime()) {
            // Il contatore è della macchina d'origine: il piano copiato resta sospeso finché non se ne sceglie uno
            $copy->is_active = false;
        }
        $copy->save();
        $copy->parts()->sync($this->plan->parts->mapWithKeys(fn (Part $p) => [$p->id => ['quantity' => $p->getRelationValue('pivot')?->getAttribute('quantity')]])->all());
        Audit::log('preventive.plan_created', $copy, $copy->title.' · '.$asset->code.' ('.__('preventive.plan.duplicate').')');

        session()->flash('status', __('preventive.plan.duplicated', ['code' => $asset->code]));

        return redirect()->route($copy->isTime() ? 'preventive.show' : 'preventive.edit', $copy);
    }

    public function delete(): RedirectResponse|Redirector
    {
        $this->authorize('preventive.manage');
        Audit::log('preventive.plan_deleted', $this->plan, $this->plan->title.' · '.$this->plan->asset->code);
        $this->plan->delete();
        session()->flash('status', __('preventive.plan.deleted'));

        return redirect()->route('preventive.index');
    }

    public function render(PlanSchedule $schedule, PlanCompliance $compliance): View
    {
        $plan = $this->plan->load(['asset.location', 'meter', 'assignee', 'checklistTemplate', 'currentOrder', 'parts']);
        $user = auth()->user();
        $canManage = $user->can('preventive.manage');

        $history = $plan->workOrders()->with('assignees')->latest('id')->limit(20)->get();

        // Prossime scadenze: se c'è un ordine aperto, quella attuale è già rappresentata da lui
        $from = $plan->hasOpenOrder() && $plan->next_due_on ? $plan->next_due_on->copy()->addDay() : today();
        $upcoming = array_slice($schedule->projected($plan, $from, today()->addYears(2)), 0, 6);

        return view('livewire.preventive.show', [
            'p' => $plan,
            'state' => $plan->dueState(),
            'history' => $history,
            'upcoming' => $upcoming,
            'compliance' => $compliance->summary(today()->subMonths(12)->addDay(), today(), $plan->id),
            'canManage' => $canManage,
            'canGenerate' => $canManage && $user->can('work_orders.create') && $plan->is_active && ! $plan->hasOpenOrder(),
            'partOptions' => $canManage ? Part::query()->where('is_active', true)->whereNotIn('id', $plan->parts->pluck('id'))->orderBy('code')->get(['id', 'code', 'name', 'unit']) : collect(),
            'assetOptions' => $canManage ? Asset::query()->whereKeyNot($plan->asset_id)->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'usage' => $plan->meter ? $schedule->dailyUsage($plan->meter) : null,
        ])->title($plan->title)->layoutData(['heading' => $plan->asset->code]);
    }
}
