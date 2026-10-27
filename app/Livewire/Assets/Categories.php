<?php

namespace App\Livewire\Assets;

use App\Models\AssetCategory;
use App\Support\Audit\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Categorie delle macchine, con icona e forma per la vista 3D.
 */
class Categories extends Component
{
    public ?int $editing = null;

    /** @var array{name: string, icon: string, shape: string, description: string} */
    public array $form = ['name' => '', 'icon' => 'factory', 'shape' => 'box', 'description' => ''];

    public function mount(): void
    {
        $this->authorize('assets.manage');
    }

    public function edit(?int $id = null): void
    {
        $this->resetErrorBag();
        $category = $id ? AssetCategory::query()->findOrFail($id) : null;
        $this->editing = $category->id ?? 0;
        $this->form = $category
            ? ['name' => $category->name, 'icon' => $category->icon, 'shape' => $category->shape, 'description' => (string) $category->description]
            : ['name' => '', 'icon' => 'factory', 'shape' => 'box', 'description' => ''];
    }

    public function save(): void
    {
        $this->authorize('assets.manage');
        $category = $this->editing ? AssetCategory::query()->findOrFail($this->editing) : new AssetCategory;
        $this->validate([
            'form.name' => ['required', 'string', 'max:80', Rule::unique('asset_categories', 'name')->ignore($category->id)],
            'form.icon' => ['required', Rule::in(AssetCategory::ICONS)],
            'form.shape' => ['required', Rule::in(AssetCategory::SHAPES)],
            'form.description' => ['nullable', 'string', 'max:500'],
        ], [], ['form.name' => __('assets.categories.name')]);

        $creating = ! $category->exists;
        $category->fill([
            'name' => trim($this->form['name']),
            'icon' => $this->form['icon'],
            'shape' => $this->form['shape'],
            'description' => trim($this->form['description']) ?: null,
        ])->save();
        Audit::log($creating ? 'assets.category_created' : 'assets.category_updated', $category, $category->name, $creating ? [] : Audit::diff($category));
        $this->editing = null;
    }

    public function delete(int $id): void
    {
        $this->authorize('assets.manage');
        $category = AssetCategory::query()->findOrFail($id);
        Audit::log('assets.category_deleted', null, $category->name);
        $category->delete(); // le macchine restano, senza categoria
        $this->editing = null;
    }

    public function render(): View
    {
        return view('livewire.assets.categories', [
            'categories' => AssetCategory::query()->withCount('assets')->orderBy('name')->get(),
            'icons' => AssetCategory::ICONS,
            'shapes' => AssetCategory::SHAPES,
        ])->title(__('assets.categories.title'))->layoutData(['heading' => __('assets.categories.title')]);
    }
}
