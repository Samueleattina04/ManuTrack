<?php

namespace App\Livewire\Inventory;

use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Support\Audit\Audit;
use App\Support\Inventory\Stock;
use App\Support\Numbers;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Scheda del ricambio: giacenze per magazzino, movimenti (prelievo, carico,
 * rettifica, trasferimento), fornitori e macchine in cui è montato.
 */
class PartShow extends Component
{
    public Part $part;

    /** issue | receive | adjust | transfer | '' */
    public string $action = '';

    /** @var array<string, string> */
    public array $move = ['warehouse_id' => '', 'to_warehouse_id' => '', 'quantity' => '', 'unit_cost' => '', 'supplier_id' => '', 'reference' => '', 'work_order' => '', 'note' => ''];

    /** @var array<int, string> scaffale per magazzino */
    public array $bins = [];

    public ?int $editingSupplier = null;

    /** @var array<string, mixed> */
    public array $supplierForm = ['supplier_id' => '', 'supplier_code' => '', 'price' => '', 'lead_time_days' => '', 'is_preferred' => false];

    public string $assetToAdd = '';

    public string $assetQty = '1';

    public function mount(Part $part): void
    {
        $this->authorize('inventory.view');
        $this->part = $part;
        // Scorciatoia dal prelievo in un ordine: /magazzino/ricambi/{id}?azione=preleva&ordine=OdL-…
        if (request()->query('azione') === 'preleva' && auth()->user()->can('inventory.move')) {
            $this->start('issue');
            $this->move['work_order'] = (string) request()->query('ordine', '');
        }
    }

    public function start(string $action): void
    {
        $this->authorize($action === 'issue' ? 'inventory.move' : 'inventory.manage');
        abort_unless(in_array($action, ['issue', 'receive', 'adjust', 'transfer'], true), 404);
        $this->resetErrorBag();
        $this->action = $action;
        // Magazzino proposto: quello con più giacenza (per uscire) o il primo attivo (per entrare)
        $best = $this->part->levels()->where('quantity', '>', 0)->orderByDesc('quantity')->value('warehouse_id')
            ?? Warehouse::query()->active()->orderBy('id')->value('id');
        $this->move = ['warehouse_id' => (string) $best, 'to_warehouse_id' => '', 'quantity' => '', 'unit_cost' => '', 'supplier_id' => (string) ($this->part->preferredSupplier()->id ?? ''), 'reference' => '', 'work_order' => '', 'note' => ''];
        if ($action === 'adjust') {
            $this->move['quantity'] = Part::formatQty($this->part->levels()->where('warehouse_id', $best)->value('quantity') ?? 0);
        }
    }

    public function updatedMoveWarehouseId(): void
    {
        if ($this->action === 'adjust') {
            $this->move['quantity'] = Part::formatQty($this->part->levels()->where('warehouse_id', (int) $this->move['warehouse_id'])->value('quantity') ?? 0);
        }
    }

    public function submit(Stock $stock): void
    {
        $this->authorize($this->action === 'issue' ? 'inventory.move' : 'inventory.manage');
        $this->resetErrorBag();
        $user = auth()->user();
        $warehouse = Warehouse::query()->find((int) $this->move['warehouse_id']);

        try {
            if (! $warehouse) {
                throw ValidationException::withMessages(['warehouse_id' => __('inventory.errors.choose_warehouse')]);
            }
            match ($this->action) {
                'issue' => $stock->issue($this->part, $warehouse, $this->move['quantity'], $user, $this->findOrder(), trim($this->move['note']) ?: null),
                'receive' => $stock->receive($this->part, $warehouse, $this->move['quantity'], $user, $this->move['unit_cost'],
                    Supplier::query()->find((int) $this->move['supplier_id']), trim($this->move['reference']) ?: null, trim($this->move['note']) ?: null),
                'adjust' => $stock->adjust($this->part, $warehouse, $this->move['quantity'], $user, $this->move['note']),
                'transfer' => $stock->transfer($this->part, $warehouse, Warehouse::query()->findOrFail((int) $this->move['to_warehouse_id']), $this->move['quantity'], $user, trim($this->move['note']) ?: null),
                default => abort(404),
            };
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('move.'.$field, $messages[0]);
            }

            return;
        } catch (ModelNotFoundException) {
            $this->addError('move.to_warehouse_id', __('inventory.errors.choose_warehouse'));

            return;
        }

        $this->dispatch('toast', message: __('inventory.moves.done.'.$this->action));
        $this->action = '';
        $this->part->refresh();
    }

    /** Ordine indicato per numero (OdL-2026-00042 oppure solo 42) */
    private function findOrder(): ?WorkOrder
    {
        $value = trim($this->move['work_order']);
        if ($value === '') {
            return null;
        }
        $order = ctype_digit($value)
            ? WorkOrder::query()->where('number', 'like', '%-'.str_pad($value, 5, '0', STR_PAD_LEFT))->latest('id')->first()
            : WorkOrder::query()->where('number', $value)->first();
        if (! $order) {
            throw ValidationException::withMessages(['work_order' => __('inventory.errors.order_not_found')]);
        }

        return $order;
    }

    public function saveBin(int $levelId): void
    {
        $this->authorize('inventory.manage');
        $level = $this->part->levels()->whereKey($levelId)->firstOrFail();
        $level->forceFill(['bin' => mb_strimwidth(trim($this->bins[$levelId] ?? ''), 0, 40, '') ?: null])->save();
        unset($this->bins[$levelId]);
    }

    public function editSupplier(?int $pivotId = null): void
    {
        $this->authorize('inventory.manage');
        $this->resetErrorBag();
        $row = $pivotId ? $this->part->suppliers()->wherePivot('id', $pivotId)->first() : null;
        $this->editingSupplier = $pivotId ?? 0;
        $this->supplierForm = $row
            ? ['supplier_id' => (string) $row->id, 'supplier_code' => (string) $row->pivot->supplier_code, 'price' => $row->pivot->price !== null ? number_format((float) $row->pivot->price, 2, ',', '') : '', 'lead_time_days' => (string) ($row->pivot->lead_time_days ?? ''), 'is_preferred' => (bool) $row->pivot->is_preferred]
            : ['supplier_id' => '', 'supplier_code' => '', 'price' => '', 'lead_time_days' => '', 'is_preferred' => ! $this->part->suppliers()->exists()];
    }

    public function saveSupplier(): void
    {
        $this->authorize('inventory.manage');
        $price = $this->supplierForm['price'] === '' ? null : Numbers::parse($this->supplierForm['price']);
        $this->validate([
            'supplierForm.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'supplierForm.supplier_code' => ['nullable', 'string', 'max:80'],
            'supplierForm.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['supplierForm.supplier_id' => __('inventory.field.supplier')]);
        if ($this->supplierForm['price'] !== '' && ($price === null || $price < 0)) {
            $this->addError('supplierForm.price', __('inventory.errors.cost'));

            return;
        }

        $data = [
            'supplier_code' => trim((string) $this->supplierForm['supplier_code']) ?: null,
            'price' => $price,
            'lead_time_days' => $this->supplierForm['lead_time_days'] === '' ? null : (int) $this->supplierForm['lead_time_days'],
            'is_preferred' => (bool) $this->supplierForm['is_preferred'],
        ];
        if ($data['is_preferred']) {
            $this->part->suppliers()->newPivotQuery()->update(['is_preferred' => false]);
        }
        $this->part->suppliers()->syncWithoutDetaching([(int) $this->supplierForm['supplier_id'] => $data]);
        Audit::log('inventory.part_supplier_saved', $this->part, $this->part->code.' · '.Supplier::query()->whereKey($this->supplierForm['supplier_id'])->value('name'));
        $this->editingSupplier = null;
    }

    public function removeSupplier(int $supplierId): void
    {
        $this->authorize('inventory.manage');
        $this->part->suppliers()->detach($supplierId);
    }

    public function addAsset(): void
    {
        abort_unless(auth()->user()->can('inventory.manage') || auth()->user()->can('assets.manage'), 403);
        $qty = Numbers::parse($this->assetQty);
        $this->validate(['assetToAdd' => ['required', 'integer', 'exists:assets,id']], [], ['assetToAdd' => __('maintenance.field.asset')]);
        if ($qty === null || $qty <= 0) {
            $this->addError('assetQty', __('inventory.errors.quantity'));

            return;
        }
        $this->part->assets()->syncWithoutDetaching([(int) $this->assetToAdd => ['quantity' => $qty]]);
        $this->assetToAdd = '';
        $this->assetQty = '1';
    }

    public function removeAsset(int $assetId): void
    {
        abort_unless(auth()->user()->can('inventory.manage') || auth()->user()->can('assets.manage'), 403);
        $this->part->assets()->detach($assetId);
    }

    public function render(): View
    {
        $p = $this->part->load(['levels.warehouse', 'suppliers', 'assets.location']);
        $user = auth()->user();

        return view('livewire.inventory.part-show', [
            'p' => $p,
            'movements' => $p->movements()->with(['warehouse', 'workOrder', 'user', 'supplier'])->limit(25)->get(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
            'suppliers' => $this->editingSupplier !== null || $this->action === 'receive' ? Supplier::query()->active()->orderBy('name')->pluck('name', 'id') : collect(),
            'activeOrders' => $this->action === 'issue'
                ? WorkOrder::query()->whereIn('status', array_map(fn ($s) => $s->value, [...WorkOrderStatus::active(), WorkOrderStatus::Completed]))
                    ->when(! $user->can('work_orders.assign'), fn ($q) => $q->assignedTo($user))
                    ->with('asset')->latest('id')->limit(50)->get()
                : collect(),
            'assetOptions' => ($user->can('inventory.manage') || $user->can('assets.manage')) ? Asset::query()->orderBy('code')->limit(2000)->get(['id', 'code', 'name']) : collect(),
            'can' => [
                'move' => $user->can('inventory.move'),
                'manage' => $user->can('inventory.manage'),
                'costs' => $user->can('inventory.view_costs'),
                'bom' => $user->can('inventory.manage') || $user->can('assets.manage'),
            ],
        ])->title($p->code.' · '.$p->name)->layoutData(['heading' => $p->code]);
    }
}
