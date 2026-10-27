<?php

namespace App\Livewire\Preventive;

use App\Models\MaintenancePlan;
use App\Support\Preventive\PlanCompliance;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Elenco dei piani preventivi con stato rispetto alla scadenza e rispetto dei piani.
 */
class Index extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'stato')]
    public string $state = '';

    #[Url(as: 'sospesi')]
    public bool $inactive = false;

    public function mount(): void
    {
        $this->authorize('preventive.view');
    }

    public function render(PlanCompliance $compliance): View
    {
        $plans = MaintenancePlan::query()
            ->with(['asset', 'meter', 'assignee', 'currentOrder'])
            ->when(! $this->inactive, fn (Builder $q) => $q->where('is_active', true))
            ->when(trim($this->search) !== '', function (Builder $q) {
                $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $like)
                    ->orWhereHas('asset', fn (Builder $a) => $a->where('code', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->get()
            ->map(fn (MaintenancePlan $p) => ['plan' => $p, 'state' => $p->dueState()]);

        $counts = [
            'all' => $plans->count(),
            'due' => $plans->where('state', 'in_scadenza')->count(),
            'overdue' => $plans->where('state', 'scaduto')->count(),
        ];
        $rows = $plans
            ->when($this->state === 'due', fn ($c) => $c->where('state', 'in_scadenza'))
            ->when($this->state === 'overdue', fn ($c) => $c->where('state', 'scaduto'))
            // Prima gli scaduti, poi in scadenza, poi per data o soglia
            ->sortBy(fn ($r) => [
                ['scaduto' => 0, 'in_scadenza' => 1, 'ok' => 2, 'fermo' => 3][$r['state']],
                $r['plan']->next_due_on?->getTimestamp() ?? PHP_INT_MAX,
                $r['plan']->title,
            ])->values();

        $from = today()->subMonths(12)->addDay();

        return view('livewire.preventive.index', [
            'rows' => $rows,
            'counts' => $counts,
            'total' => MaintenancePlan::query()->count(),
            'compliance' => $compliance->summary($from, today()),
            'canManage' => auth()->user()->can('preventive.manage'),
        ])->title(__('preventive.title'))->layoutData(['heading' => __('preventive.title')]);
    }
}
