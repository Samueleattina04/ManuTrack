<?php

namespace App\Livewire\WorkOrders;

use App\Enums\Priority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Asset;
use App\Models\WorkOrder;
use App\Support\Filters\WorkOrderFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Ordini di lavoro raggruppati per fase, con filtri. Chi esegue gli ordini
 * parte dai propri ("Miei"); chi li assegna da tutti.
 */
class Index extends Component
{
    use WithPagination;

    /** Gruppi di stati mostrati come schede */
    public const TABS = [
        'da_fare' => ['aperto', 'pianificato'],
        'in_corso' => ['in_corso', 'in_attesa'],
        'da_chiudere' => ['completato'],
        'chiusi' => ['chiuso', 'annullato'],
        'tutti' => [],
    ];

    #[Url(as: 'vista')]
    public string $tab = 'da_fare';

    #[Url(as: 'miei')]
    public bool $mine = false;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'priorita')]
    public string $priority = '';

    #[Url(as: 'tipo')]
    public string $type = '';

    #[Url(as: 'macchina')]
    public string $asset = '';

    public function paginationView(): string
    {
        return 'components.pagination';
    }

    public function mount(): void
    {
        $this->authorize('work_orders.view');
        if (! request()->has('miei') && ! auth()->user()->can('work_orders.assign')) {
            $this->mine = true;
        }
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'da_fare';
        }
    }

    public function updating(string $field): void
    {
        if (in_array($field, ['tab', 'mine', 'search', 'priority', 'type', 'asset'], true)) {
            $this->resetPage();
        }
    }

    public function filters(): WorkOrderFilters
    {
        return new WorkOrderFilters($this->tab, $this->mine, $this->search, $this->priority, $this->type, $this->asset);
    }

    /**
     * @return Builder<WorkOrder>
     */
    private function query(): Builder
    {
        return $this->filters()->base(auth()->user());
    }

    public function render(): View
    {
        $statusCounts = $this->query()->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $tabCounts = collect(self::TABS)->map(fn (array $statuses) => $statuses === [] ? $statusCounts->sum() : $statusCounts->only($statuses)->sum());

        $orders = $this->filters()->query(auth()->user())->with(['asset', 'assignees'])->paginate(25);

        return view('livewire.work-orders.index', [
            'orders' => $orders,
            'tabCounts' => $tabCounts,
            'priorities' => Priority::cases(),
            'types' => WorkOrderType::cases(),
            'assetFilter' => ctype_digit($this->asset) ? Asset::query()->find((int) $this->asset) : null,
            'overdue' => $this->query()->whereIn('status', array_map(fn ($s) => $s->value, WorkOrderStatus::active()))->where('due_at', '<', now())->count(),
        ])->title(__('maintenance.order.title'))->layoutData(['heading' => __('maintenance.order.title')]);
    }
}
