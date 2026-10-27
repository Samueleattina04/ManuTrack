<?php

namespace App\Livewire\Inventory;

use App\Models\Location;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Support\Audit\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Magazzini (anche più d'uno: centrale, di reparto, furgone…).
 * Un magazzino con giacenza non si elimina: si disattiva dopo averlo svuotato.
 */
class Warehouses extends Component
{
    public ?int $editing = null;

    /** @var array{name: string, code: string, location_id: string, description: string, is_active: bool} */
    public array $form = ['name' => '', 'code' => '', 'location_id' => '', 'description' => '', 'is_active' => true];

    public function mount(): void
    {
        $this->authorize('inventory.manage');
    }

    public function edit(?int $id = null): void
    {
        $this->authorize('inventory.manage');
        $this->resetErrorBag();
        $w = $id ? Warehouse::query()->findOrFail($id) : null;
        $this->editing = $w->id ?? 0;
        $this->form = $w
            ? ['name' => $w->name, 'code' => (string) $w->code, 'location_id' => (string) ($w->location_id ?? ''), 'description' => (string) $w->description, 'is_active' => $w->is_active]
            : ['name' => '', 'code' => '', 'location_id' => '', 'description' => '', 'is_active' => true];
    }

    public function save(): void
    {
        $this->authorize('inventory.manage');
        $w = $this->editing ? Warehouse::query()->findOrFail($this->editing) : new Warehouse;
        $this->validate([
            'form.name' => ['required', 'string', 'max:120', Rule::unique('warehouses', 'name')->ignore($w->id)],
            'form.code' => ['nullable', 'string', 'max:20'],
            'form.location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'form.description' => ['nullable', 'string', 'max:500'],
        ], [], ['form.name' => __('inventory.field.name')]);

        if ($w->exists && $w->is_active && ! $this->form['is_active'] && StockLevel::query()->where('warehouse_id', $w->id)->where('quantity', '>', 0)->exists()) {
            $this->addError('form.is_active', __('inventory.warehouses.not_empty'));

            return;
        }

        $creating = ! $w->exists;
        $w->fill([
            'name' => trim($this->form['name']),
            'code' => trim($this->form['code']) ?: null,
            'location_id' => $this->form['location_id'] !== '' ? (int) $this->form['location_id'] : null,
            'description' => trim($this->form['description']) ?: null,
            'is_active' => (bool) $this->form['is_active'],
        ])->save();
        Audit::log($creating ? 'inventory.warehouse_created' : 'inventory.warehouse_updated', $w, $w->name, $creating ? [] : Audit::diff($w));
        $this->editing = null;
    }

    public function render(): View
    {
        return view('livewire.inventory.warehouses', [
            'warehouses' => Warehouse::query()->with('location')
                ->withCount(['levels as parts_count' => fn ($q) => $q->where('quantity', '>', 0)])
                ->withSum('levels as total_qty', 'quantity')
                ->orderByDesc('is_active')->orderBy('name')->get(),
            'locations' => Location::labels(),
        ])->title(__('inventory.warehouses.title'))->layoutData(['heading' => __('inventory.warehouses.title')]);
    }
}
