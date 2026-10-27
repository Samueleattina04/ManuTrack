<?php

namespace App\Livewire\Inventory;

use App\Enums\MovementType;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Registro dei movimenti di magazzino, con filtri.
 */
class Movements extends Component
{
    use WithPagination;

    #[Url(as: 'tipo')]
    public string $type = '';

    #[Url(as: 'magazzino')]
    public string $warehouse = '';

    #[Url(as: 'ricambio')]
    public string $part = '';

    #[Url(as: 'dal')]
    public string $from = '';

    #[Url(as: 'al')]
    public string $to = '';

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
        $this->resetPage();
    }

    public function render(): View
    {
        $movements = StockMovement::query()
            ->with(['part', 'warehouse', 'workOrder', 'user', 'supplier'])
            ->when(MovementType::tryFrom($this->type), fn (Builder $q) => $q->where('type', $this->type))
            ->when(ctype_digit($this->warehouse), fn (Builder $q) => $q->where('warehouse_id', (int) $this->warehouse))
            ->when(ctype_digit($this->part), fn (Builder $q) => $q->where('part_id', (int) $this->part))
            ->when($this->validDate($this->from), fn (Builder $q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->validDate($this->to), fn (Builder $q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(40);

        return view('livewire.inventory.movements', [
            'movements' => $movements,
            'types' => MovementType::cases(),
            'warehouses' => Warehouse::query()->orderBy('name')->pluck('name', 'id'),
            'partFilter' => ctype_digit($this->part) ? Part::query()->find((int) $this->part) : null,
            'canCosts' => auth()->user()->can('inventory.view_costs'),
        ])->title(__('inventory.movements.title'))->layoutData(['heading' => __('inventory.movements.title')]);
    }

    private function validDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
