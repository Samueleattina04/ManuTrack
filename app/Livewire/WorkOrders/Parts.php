<?php

namespace App\Livewire\WorkOrders;

use App\Enums\MovementType;
use App\Models\Part;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Support\Inventory\Stock;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Ricambi usati in un ordine di lavoro: prelievo (proponendo prima la distinta
 * della macchina), reso di quanto non è servito, costo dei ricambi.
 * Il prelievo è per chi esegue l'ordine e può muovere il magazzino.
 */
class Parts extends Component
{
    public WorkOrder $workOrder;

    public bool $adding = false;

    public string $search = '';

    public ?int $partId = null;

    public string $warehouseId = '';

    public string $quantity = '1';

    public ?int $returning = null;

    public string $returnQuantity = '';

    public function mount(WorkOrder $workOrder): void
    {
        $this->workOrder = $workOrder;
    }

    private function canMove(): bool
    {
        $user = auth()->user();

        return $user->can('execute', $this->workOrder) && $user->can('inventory.move');
    }

    public function open(): void
    {
        abort_unless($this->canMove(), 403);
        $this->adding = true;
        $this->returning = null;
        $this->reset('search', 'partId', 'warehouseId');
        $this->quantity = '1';
        $this->resetErrorBag();
    }

    public function choose(int $partId): void
    {
        abort_unless($this->canMove(), 403);
        $part = Part::query()->where('is_active', true)->findOrFail($partId);
        $this->partId = $part->id;
        // Il magazzino con più pezzi disponibili
        $this->warehouseId = (string) (StockLevel::query()->where('part_id', $part->id)->where('quantity', '>', 0)
            ->whereHas('warehouse', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('quantity')->value('warehouse_id') ?? '');
        $this->resetErrorBag();
    }

    public function issue(Stock $stock): void
    {
        abort_unless($this->canMove(), 403);
        $this->resetErrorBag();
        $part = Part::query()->where('is_active', true)->find($this->partId);
        $warehouse = Warehouse::query()->find((int) $this->warehouseId);
        if (! $part || ! $warehouse) {
            $this->addError('warehouseId', __('inventory.errors.choose_warehouse'));

            return;
        }

        try {
            $stock->issue($part, $warehouse, $this->quantity, auth()->user(), $this->workOrder);
        } catch (ValidationException $e) {
            $this->addError('quantity', collect($e->errors())->flatten()->first());

            return;
        }

        $this->adding = false;
        $this->reset('search', 'partId', 'warehouseId');
        $this->dispatch('parts-changed');
        $this->dispatch('toast', message: __('inventory.moves.done.issue'));
    }

    public function startReturn(int $partId): void
    {
        abort_unless($this->canMove(), 403);
        $this->returning = $partId;
        $this->adding = false;
        $this->returnQuantity = Part::formatQty(app(Stock::class)->netIssued($this->workOrder, Part::query()->findOrFail($partId)));
        $this->resetErrorBag();
    }

    public function giveBack(Stock $stock): void
    {
        abort_unless($this->canMove() && $this->returning !== null, 403);
        $this->resetErrorBag();
        $part = Part::query()->findOrFail($this->returning);
        // Torna nel magazzino da cui è stato prelevato l'ultima volta
        $warehouseId = StockMovement::query()->where('work_order_id', $this->workOrder->id)->where('part_id', $part->id)
            ->where('type', MovementType::Issue->value)->latest('id')->value('warehouse_id');
        $warehouse = Warehouse::query()->findOrFail($warehouseId);

        try {
            $stock->giveBack($part, $warehouse, $this->returnQuantity, auth()->user(), $this->workOrder);
        } catch (ValidationException $e) {
            $this->addError('returnQuantity', collect($e->errors())->flatten()->first());

            return;
        }

        $this->returning = null;
        $this->dispatch('parts-changed');
        $this->dispatch('toast', message: __('inventory.moves.done.return'));
    }

    public function render(): View
    {
        $user = auth()->user();
        $movements = $this->workOrder->stockMovements()->with('part')->get();

        /** @var Collection<int, array{part: Part, quantity: float, cost: float}> $used */
        $used = $movements->groupBy('part_id')->map(fn (Collection $ms) => [
            'part' => $ms->first()->part,
            'quantity' => round(-(float) $ms->sum('quantity'), 4),
            'cost' => round((float) $ms->sum(fn (StockMovement $m) => -(float) $m->quantity * (float) $m->unit_cost), 2),
        ])->filter(fn ($row) => $row['quantity'] > 0.00001 || $row['cost'] != 0.0)->values();

        $suggestions = collect();
        $chosen = null;
        $levels = collect();
        if ($this->adding) {
            if ($this->partId) {
                $chosen = Part::query()->find($this->partId);
                $levels = StockLevel::query()->with('warehouse')->where('part_id', $this->partId)
                    ->whereHas('warehouse', fn ($q) => $q->where('is_active', true))->get();
            } elseif (trim($this->search) !== '') {
                $suggestions = Part::query()->where('is_active', true)->search($this->search)->orderBy('code')->limit(8)->get();
            } elseif ($this->workOrder->asset_id) {
                // Senza ricerca: la distinta ricambi della macchina
                $suggestions = Part::query()->where('is_active', true)
                    ->whereHas('assets', fn ($q) => $q->whereKey($this->workOrder->asset_id))->orderBy('code')->get();
            }
        }

        return view('livewire.work-orders.parts', [
            'used' => $used,
            'canMove' => $this->canMove(),
            'canCosts' => $user->can('viewCosts', $this->workOrder),
            'suggestions' => $suggestions,
            'fromBom' => $this->adding && ! $this->partId && trim($this->search) === '',
            'chosen' => $chosen,
            'levels' => $levels,
        ]);
    }
}
