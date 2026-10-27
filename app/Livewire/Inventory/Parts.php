<?php

namespace App\Livewire\Inventory;

use App\Models\Part;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Ricambi: ricerca (anche per codice del costruttore o del fornitore), filtri,
 * giacenze e stato della scorta.
 */
class Parts extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'scorta')]
    public string $stock = '';

    #[Url(as: 'magazzino')]
    public string $warehouse = '';

    #[Url(as: 'categoria')]
    public string $category = '';

    #[Url(as: 'disattivati')]
    public bool $inactive = false;

    public function paginationView(): string
    {
        return 'components.pagination';
    }

    public function mount(): void
    {
        $this->authorize('inventory.view');
    }

    public function updating(string $field): void
    {
        if (in_array($field, ['search', 'stock', 'warehouse', 'category', 'inactive'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return Builder<Part>
     */
    private function query(bool $withStock = true): Builder
    {
        return Part::query()
            ->where('is_active', ! $this->inactive)
            ->search($this->search)
            ->when($this->category !== '', fn (Builder $q) => $q->where('category', $this->category))
            ->when(ctype_digit($this->warehouse), fn (Builder $q) => $q->whereHas('levels', fn (Builder $l) => $l->where('warehouse_id', (int) $this->warehouse)->where('quantity', '>', 0)))
            ->when($withStock && $this->stock === 'sotto', fn (Builder $q) => $q->belowMinimum())
            ->when($withStock && $this->stock === 'esauriti', fn (Builder $q) => $q->where('stock_total', '<=', 0));
    }

    public function render(): View
    {
        $parts = $this->query()->with(['levels.warehouse'])->orderBy('code')->paginate(30);
        $base = $this->query(withStock: false);

        return view('livewire.inventory.parts', [
            'parts' => $parts,
            'activeFilters' => count(array_filter([$this->warehouse !== '', $this->category !== '', $this->inactive])),
            'counts' => [
                'tutti' => (clone $base)->count(),
                'sotto' => (clone $base)->belowMinimum()->count(),
                'esauriti' => (clone $base)->where('stock_total', '<=', 0)->count(),
            ],
            'warehouses' => Warehouse::query()->active()->orderBy('name')->pluck('name', 'id'),
            'categories' => Part::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'canManage' => auth()->user()->can('inventory.manage'),
            'canCosts' => auth()->user()->can('inventory.view_costs'),
            'total' => Part::query()->count(),
        ])->title(__('inventory.title'))->layoutData(['heading' => __('inventory.title')]);
    }
}
