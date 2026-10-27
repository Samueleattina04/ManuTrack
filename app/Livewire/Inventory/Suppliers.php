<?php

namespace App\Livewire\Inventory;

use App\Models\Supplier;
use App\Support\Audit\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Fornitori, con i ricambi che forniscono. Chi vede il magazzino li consulta,
 * chi lo gestisce li modifica.
 */
class Suppliers extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public ?int $editing = null;

    public ?int $open = null;

    /** @var array<string, mixed> */
    public array $form = ['name' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'website' => '', 'notes' => '', 'is_active' => true];

    public function mount(): void
    {
        $this->authorize('inventory.view');
    }

    public function edit(?int $id = null): void
    {
        $this->authorize('inventory.manage');
        $this->resetErrorBag();
        $s = $id ? Supplier::query()->findOrFail($id) : null;
        $this->editing = $s->id ?? 0;
        $this->form = $s ? $s->only(array_keys($this->form)) : ['name' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'website' => '', 'notes' => '', 'is_active' => true];
        foreach ($this->form as $k => $v) {
            $this->form[$k] = $k === 'is_active' ? (bool) $v : (string) ($v ?? '');
        }
    }

    public function save(): void
    {
        $this->authorize('inventory.manage');
        $s = $this->editing ? Supplier::query()->findOrFail($this->editing) : new Supplier;
        $this->validate([
            'form.name' => ['required', 'string', 'max:150', Rule::unique('suppliers', 'name')->ignore($s->id)],
            'form.contact_name' => ['nullable', 'string', 'max:120'],
            'form.email' => ['nullable', 'email', 'max:190'],
            'form.phone' => ['nullable', 'string', 'max:40'],
            'form.website' => ['nullable', 'url', 'max:190'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
        ], [], collect(array_keys($this->form))->mapWithKeys(fn ($k) => ['form.'.$k => __('inventory.supplier_field.'.$k)])->all());

        $creating = ! $s->exists;
        $s->fill(collect($this->form)->map(fn ($v, $k) => $k === 'is_active' ? (bool) $v : (trim((string) $v) ?: null))->all())->save();
        Audit::log($creating ? 'inventory.supplier_created' : 'inventory.supplier_updated', $s, $s->name, $creating ? [] : Audit::diff($s));
        $this->editing = null;
    }

    public function toggle(int $id): void
    {
        $this->open = $this->open === $id ? null : $id;
    }

    public function render(): View
    {
        $like = '%'.addcslashes(trim($this->search), '%_\\').'%';

        return view('livewire.inventory.suppliers', [
            'suppliers' => Supplier::query()->withCount('parts')
                ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('contact_name', 'like', $like)->orWhere('email', 'like', $like)))
                ->orderByDesc('is_active')->orderBy('name')->get(),
            'openParts' => $this->open ? Supplier::query()->find($this->open)?->parts()->orderBy('code')->get() : null,
            'canManage' => auth()->user()->can('inventory.manage'),
            'canCosts' => auth()->user()->can('inventory.view_costs'),
        ])->title(__('inventory.suppliers.title'))->layoutData(['heading' => __('inventory.suppliers.title')]);
    }
}
