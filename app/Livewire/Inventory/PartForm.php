<?php

namespace App\Livewire\Inventory;

use App\Actions\Inventory\SavePart;
use App\Models\Part;
use App\Models\Warehouse;
use App\Support\Inventory\Stock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Nuovo ricambio (con giacenza iniziale facoltativa) o modifica dell'anagrafica.
 */
class PartForm extends Component
{
    public ?Part $part = null;

    /** @var array<string, mixed> */
    public array $form = [
        'code' => '', 'name' => '', 'category' => '', 'unit' => 'pz', 'manufacturer' => '', 'manufacturer_code' => '',
        'description' => '', 'min_stock' => '', 'reorder_qty' => '', 'is_active' => true,
    ];

    /** @var array{warehouse_id: string, quantity: string, unit_cost: string, bin: string} */
    public array $initial = ['warehouse_id' => '', 'quantity' => '', 'unit_cost' => '', 'bin' => ''];

    public function mount(?Part $part = null): void
    {
        $this->authorize('inventory.manage');
        if ($part?->exists) {
            $this->part = $part;
            foreach (array_keys($this->form) as $k) {
                $v = $part->getAttribute($k);
                $this->form[$k] = is_bool($v) ? $v : (in_array($k, ['min_stock', 'reorder_qty'], true) && $v !== null ? Part::formatQty($v) : (string) ($v ?? ''));
            }
        } else {
            $this->initial['warehouse_id'] = (string) (Warehouse::query()->active()->orderBy('id')->value('id') ?? '');
        }
    }

    public function save(SavePart $save, Stock $stock): RedirectResponse|Redirector|null
    {
        $this->authorize('inventory.manage');
        $this->resetErrorBag();

        try {
            $part = DB::transaction(function () use ($save, $stock) {
                $part = $save->handle(array_map(fn ($v) => $v === '' ? null : $v, $this->form), $this->part);
                if (! $this->part && trim($this->initial['quantity']) !== '') {
                    $warehouse = Warehouse::query()->active()->find((int) $this->initial['warehouse_id']);
                    if (! $warehouse) {
                        throw ValidationException::withMessages(['initial.warehouse_id' => __('inventory.errors.choose_warehouse')]);
                    }
                    $part->levels()->updateOrCreate(['warehouse_id' => $warehouse->id], ['bin' => trim($this->initial['bin']) ?: null]);
                    $stock->receive($part, $warehouse, $this->initial['quantity'], auth()->user(), $this->initial['unit_cost'], note: __('inventory.part.initial_note'));
                }

                return $part;
            });
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(str_contains($field, '.') ? $field : (array_key_exists($field, $this->form) ? 'form.'.$field : 'initial.'.$field), $messages[0]);
            }

            return null;
        }

        session()->flash('status', __($this->part ? 'inventory.part.saved' : 'inventory.part.created', ['code' => $part->code]));

        return redirect()->route('inventory.parts.show', $part);
    }

    public function render(): View
    {
        $title = $this->part ? __('inventory.part.edit', ['code' => $this->part->code]) : __('inventory.part.new');

        return view('livewire.inventory.part-form', [
            'warehouses' => Warehouse::query()->active()->orderBy('name')->pluck('name', 'id'),
            'categories' => Part::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'units' => Part::UNITS,
            'canCosts' => auth()->user()->can('inventory.view_costs'),
        ])->title($title)->layoutData(['heading' => $title]);
    }
}
