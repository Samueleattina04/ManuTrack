<?php

namespace App\Livewire\Inventory;

use App\Models\Part;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Da riordinare: ricambi alla scorta minima o sotto, raggruppati per fornitore
 * preferito, con la quantità suggerita. Si copia o si stampa per l'ordine.
 */
class Reorder extends Component
{
    public function mount(): void
    {
        $this->authorize('inventory.manage');
    }

    public function render(): View
    {
        $parts = Part::query()->where('is_active', true)->belowMinimum()->with('suppliers')->orderBy('code')->get();
        $groups = $parts->groupBy(fn (Part $p) => $p->preferredSupplier()->id ?? 0)
            ->map(fn ($items, $supplierId) => [
                'supplier' => $supplierId ? $items->first()->preferredSupplier() : null,
                'parts' => $items,
                'total' => $items->sum(fn (Part $p) => $p->suggestedOrder() * (float) ($p->preferredSupplier()?->pivot->price ?? $p->unit_cost)),
            ])
            ->sortBy(fn ($g) => $g['supplier'] ? $g['supplier']->name : 'zzz')->values();

        return view('livewire.inventory.reorder', [
            'groups' => $groups,
            'count' => $parts->count(),
        ])->title(__('inventory.reorder.title'))->layoutData(['heading' => __('inventory.reorder.title')]);
    }
}
