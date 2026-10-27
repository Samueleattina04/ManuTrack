<?php

namespace App\Livewire\Preventive;

use App\Models\MaintenancePlan;
use App\Models\WorkOrder;
use App\Support\Preventive\PlanSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Calendario mensile della preventiva: ordini già generati (con il loro stato)
 * e scadenze previste dei piani (stimate per quelli a contatore).
 */
class Calendar extends Component
{
    #[Url(as: 'mese')]
    public string $month = '';

    #[Url(as: 'miei')]
    public bool $mine = false;

    public function mount(): void
    {
        $this->authorize('preventive.view');
    }

    public function move(int $delta): void
    {
        $this->month = $this->current()->addMonthsNoOverflow($delta)->format('Y-m');
    }

    public function today(): void
    {
        $this->month = '';
    }

    private function current(): CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $this->month, $m) && checkdate((int) $m[2], 1, (int) $m[1])) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], 1);
        }

        return CarbonImmutable::today()->startOfMonth();
    }

    public function render(PlanSchedule $schedule): View
    {
        $start = $this->current();
        $end = $start->endOfMonth();
        $user = auth()->user();

        /** @var array<string, list<array<string, mixed>>> $days */
        $days = [];

        // Ordini preventivi con scadenza nel mese
        WorkOrder::query()->whereNotNull('maintenance_plan_id')->with(['asset', 'assignees'])
            ->whereBetween('due_at', [$start->startOfDay(), $end->endOfDay()])
            ->when($this->mine, fn ($q) => $q->whereHas('assignees', fn ($a) => $a->whereKey($user->id)))
            ->orderBy('due_at')->get()
            ->each(function (WorkOrder $o) use (&$days): void {
                $days[$o->due_at->toDateString()][] = [
                    'kind' => 'order', 'title' => $o->title, 'asset' => $o->asset?->code, 'url' => route('work-orders.show', $o),
                    'status' => $o->status, 'overdue' => $o->isOverdue(), 'number' => $o->number,
                    'done' => ! $o->status->isActive(),
                ];
            });

        // Scadenze previste dei piani attivi
        MaintenancePlan::query()->active()->with(['asset', 'meter', 'currentOrder'])
            ->when($this->mine, fn ($q) => $q->where('assignee_id', $user->id))
            ->get()
            ->each(function (MaintenancePlan $p) use (&$days, $schedule, $start, $end): void {
                // La scadenza attuale, se ha già un ordine, è rappresentata dall'ordine
                $from = $p->hasOpenOrder() && $p->next_due_on ? $p->next_due_on->copy()->addDay() : $start;
                foreach ($schedule->projected($p, $from->max($start), $end) as $o) {
                    $days[$o['date']->toDateString()][] = [
                        'kind' => 'planned', 'title' => $p->title, 'asset' => $p->asset->code, 'url' => route('preventive.show', $p),
                        'estimated' => $o['estimated'], 'overdue' => $o['date']->lt(CarbonImmutable::today()),
                    ];
                }
            });
        ksort($days);

        // Griglia da lunedì a domenica
        $gridStart = $start->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $end->endOfWeek(CarbonImmutable::SUNDAY);
        $weeks = [];
        for ($d = $gridStart; $d->lte($gridEnd); $d = $d->addDay()) {
            $weeks[intdiv((int) $gridStart->diffInDays($d), 7)][] = $d;
        }

        return view('livewire.preventive.calendar', [
            'start' => $start,
            'weeks' => $weeks,
            'days' => $days,
            'today' => CarbonImmutable::today()->toDateString(),
            'weekdays' => collect(range(0, 6))->map(fn ($i) => $gridStart->addDays($i)->translatedFormat('D'))->all(),
        ])->title(__('preventive.calendar.title'))->layoutData(['heading' => __('preventive.calendar.title')]);
    }
}
