<?php

namespace App\Livewire\Assets;

use App\Enums\AssetStatus;
use App\Enums\Criticality;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Support\Filters\AssetFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Elenco delle macchine con ricerca, filtri e scelta per la stampa delle etichette QR.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'ubicazione')]
    public string $location = '';

    #[Url(as: 'categoria')]
    public string $category = '';

    #[Url(as: 'stato')]
    public string $status = '';

    #[Url(as: 'criticita')]
    public string $criticality = '';

    #[Url(as: 'ordine')]
    public string $sort = 'code';

    /** @var list<int> Macchine scelte per le etichette */
    public array $selected = [];

    public function paginationView(): string
    {
        return 'components.pagination';
    }

    public function mount(): void
    {
        $this->authorize('assets.view');
    }

    public function updating(string $field): void
    {
        if (in_array($field, ['search', 'location', 'category', 'status', 'criticality', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'location', 'category', 'status', 'criticality');
        $this->resetPage();
    }

    /** Sceglie (o toglie) tutte le macchine che rispettano i filtri, fino a 500 */
    public function selectAll(): void
    {
        $ids = $this->query()->limit(500)->pluck('assets.id')->map(fn ($id) => (int) $id)->all();
        $this->selected = array_values(array_diff($ids, $this->selected)) === [] ? [] : array_values(array_unique([...$this->selected, ...$ids]));
    }

    public function filters(): AssetFilters
    {
        return new AssetFilters($this->search, $this->location, $this->category, $this->status, $this->criticality, $this->sort);
    }

    /**
     * @return Builder<Asset>
     */
    private function query(bool $withStatus = true): Builder
    {
        return $this->filters()->query($withStatus);
    }

    public function render(): View
    {
        $assets = $this->filters()->sorted()->with(['category', 'cover'])->paginate(25);

        $counts = $this->query(withStatus: false)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n);

        $user = auth()->user();

        return view('livewire.assets.index', [
            'assets' => $assets,
            'counts' => $counts,
            'total' => $counts->sum(),
            'everything' => Asset::query()->count(),
            'locations' => Location::labels(),
            'categories' => AssetCategory::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => AssetStatus::cases(),
            'criticalities' => Criticality::cases(),
            'canManage' => $user->can('assets.manage'),
            'filtered' => $this->search !== '' || $this->location !== '' || $this->category !== '' || $this->status !== '' || $this->criticality !== '',
        ])->title(__('assets.title'))->layoutData(['heading' => __('assets.title')]);
    }
}
