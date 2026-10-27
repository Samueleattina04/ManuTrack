<?php

namespace App\Livewire\Assets;

use App\Enums\LocationType;
use App\Models\Asset;
use App\Models\Location;
use App\Support\Audit\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Ubicazioni dello stabilimento: sito > area > linea.
 */
class Locations extends Component
{
    /** null = modulo chiuso, 0 = nuova ubicazione */
    public ?int $editing = null;

    /** @var array{parent_id: string, name: string, code: string, description: string} */
    public array $form = ['parent_id' => '', 'name' => '', 'code' => '', 'description' => ''];

    public function mount(): void
    {
        $this->authorize('assets.view');
    }

    public function create(?int $parentId = null): void
    {
        $this->authorize('assets.manage');
        $this->resetErrorBag();
        $this->editing = 0;
        $this->form = ['parent_id' => $parentId ? (string) $parentId : '', 'name' => '', 'code' => '', 'description' => ''];
    }

    public function edit(int $id): void
    {
        $this->authorize('assets.manage');
        $this->resetErrorBag();
        $location = Location::query()->findOrFail($id);
        $this->editing = $location->id;
        $this->form = [
            'parent_id' => (string) ($location->parent_id ?? ''),
            'name' => $location->name,
            'code' => (string) $location->code,
            'description' => (string) $location->description,
        ];
    }

    public function save(): void
    {
        $this->authorize('assets.manage');
        $location = $this->editing ? Location::query()->findOrFail($this->editing) : new Location;
        $parentId = $this->form['parent_id'] !== '' ? (int) $this->form['parent_id'] : null;

        $this->validate([
            'form.parent_id' => ['nullable', 'integer', Rule::in(array_keys($this->parentOptions($location)))],
            'form.name' => ['required', 'string', 'max:120', Rule::unique('locations', 'name')->where('parent_id', $parentId)->ignore($location->id)],
            'form.code' => ['nullable', 'string', 'max:30'],
            'form.description' => ['nullable', 'string', 'max:500'],
        ], [], [
            'form.parent_id' => __('assets.locations.parent'),
            'form.name' => __('assets.locations.name'),
            'form.code' => __('assets.locations.code'),
            'form.description' => __('assets.locations.description'),
        ]);

        $creating = ! $location->exists;
        $location->fill([
            'parent_id' => $parentId,
            'name' => trim($this->form['name']),
            'code' => trim($this->form['code']) ?: null,
            'description' => trim($this->form['description']) ?: null,
        ])->save();

        Audit::log($creating ? 'assets.location_created' : 'assets.location_updated', $location, $location->fullName(), $creating ? [] : Audit::diff($location));
        $this->editing = null;
        $this->dispatch('toast', message: __('assets.locations.saved', ['name' => $location->name]));
    }

    public function delete(int $id): void
    {
        $this->authorize('assets.manage');
        $location = Location::query()->withCount(['children', 'assets'])->findOrFail($id);
        if ($location->children_count > 0 || $location->assets_count > 0) {
            $this->dispatch('toast', message: __('assets.locations.not_empty'), tone: 'ko');

            return;
        }
        $name = $location->fullName();
        $location->delete();
        Audit::log('assets.location_deleted', null, $name);
        $this->editing = null;
    }

    /**
     * Genitori possibili: lo stesso livello di prima (lo spostamento non cambia
     * il tipo), oppure qualunque sito o area per una nuova ubicazione.
     *
     * @return array<int, string>
     */
    private function parentOptions(Location $location): array
    {
        $labels = Location::labels();
        $query = Location::query();

        if ($location->exists) {
            if ($location->depth === 0) {
                return [];
            }
            $query->where('depth', $location->depth - 1);
        } else {
            $query->where('depth', '<', LocationType::Line->depth());
        }

        return $query->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => $labels[$id] ?? ''])->sort()->all();
    }

    public function render(): View
    {
        $editingLocation = $this->editing ? Location::query()->find($this->editing) : null;

        return view('livewire.assets.locations', [
            'tree' => Location::tree(),
            'counts' => Asset::query()->toBase()->selectRaw('location_id, count(*) as n')->groupBy('location_id')->pluck('n', 'location_id'),
            'parents' => $this->editing !== null ? $this->parentOptions($editingLocation ?? new Location) : [],
            'editingLocation' => $editingLocation,
            'canManage' => auth()->user()->can('assets.manage'),
        ])->title(__('assets.locations.title'))->layoutData(['heading' => __('assets.locations.title')]);
    }
}
