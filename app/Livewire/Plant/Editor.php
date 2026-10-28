<?php

namespace App\Livewire\Plant;

use App\Models\Asset;
use App\Models\LayoutItem;
use App\Models\Location;
use App\Models\PlantLayout;
use App\Support\Plant\LayoutEditor;
use App\Support\Plant\PlantData;
use App\Support\Plant\Shapes;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Editor della pianta, in 2D dall'alto: macchine e zone si trascinano (anche con il dito)
 * o si spostano con la tastiera. Senza pianta (rotta "nuova") mostra solo il modulo.
 */
class Editor extends Component
{
    public ?PlantLayout $plant = null;

    /** @var array{name: string, location_id: string, width: string, depth: string} */
    public array $form = ['name' => '', 'location_id' => '', 'width' => '40', 'depth' => '25'];

    public ?int $selected = null;

    /** @var array{w: string, d: string, h: string, rotation: string, label: string} */
    public array $item = ['w' => '', 'd' => '', 'h' => '', 'rotation' => '0', 'label' => ''];

    public string $addAsset = '';

    public string $zoneLocation = '';

    public string $zoneLabel = '';

    public function mount(?PlantLayout $plant = null): void
    {
        $this->authorize('assets.manage');
        $this->plant = $plant?->exists ? $plant : null;
        if ($this->plant) {
            $this->form = [
                'name' => $this->plant->name,
                'location_id' => (string) ($this->plant->location_id ?? ''),
                'width' => rtrim(rtrim((string) $this->plant->width, '0'), '.'),
                'depth' => rtrim(rtrim((string) $this->plant->depth, '0'), '.'),
            ];
        }
    }

    public function saveLayout(LayoutEditor $editor): RedirectResponse|Redirector|null
    {
        $this->authorize('assets.manage');
        $this->validate([
            'form.name' => ['required', 'string', 'max:80'],
            'form.location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'form.width' => ['required', 'numeric'],
            'form.depth' => ['required', 'numeric'],
        ], attributes: ['form.name' => __('plant.form.name'), 'form.width' => __('plant.form.width'), 'form.depth' => __('plant.form.depth')]);

        $creating = $this->plant === null;
        $layout = $this->wrap(fn () => $editor->saveLayout($this->plant, [
            'name' => $this->form['name'],
            'location_id' => $this->form['location_id'] !== '' ? (int) $this->form['location_id'] : null,
            'width' => $this->form['width'],
            'depth' => $this->form['depth'],
        ]), 'form.');
        if ($layout === null) {
            return null;
        }
        if ($creating) {
            return redirect()->route('plant.edit', $layout);
        }
        $this->plant = $layout->refresh();
        $this->dispatch('toast', message: __('plant.form.saved'));

        return null;
    }

    public function deleteLayout(LayoutEditor $editor): RedirectResponse|Redirector
    {
        $this->authorize('assets.manage');
        abort_unless($this->plant !== null, 404);
        $editor->deleteLayout($this->plant);
        session()->flash('status', __('plant.form.deleted'));

        return redirect()->route('plant.index');
    }

    public function addAssetToLayout(LayoutEditor $editor): void
    {
        $this->authorize('assets.manage');
        $asset = ctype_digit($this->addAsset) ? Asset::query()->find((int) $this->addAsset) : null;
        if (! $this->plant || ! $asset) {
            return;
        }
        $item = $this->wrap(fn () => $editor->addAsset($this->plant, $asset), '', 'addAsset');
        if ($item) {
            $this->addAsset = '';
            $this->select($item->id);
        }
    }

    /** Mette nella pianta tutte le macchine dell'ubicazione che ancora mancano (al massimo 200) */
    public function addAllAssets(LayoutEditor $editor): void
    {
        $this->authorize('assets.manage');
        if (! $this->plant) {
            return;
        }
        foreach ($this->unplaced()->limit(200)->get() as $asset) {
            $editor->addAsset($this->plant, $asset);
        }
    }

    public function addZone(LayoutEditor $editor): void
    {
        $this->authorize('assets.manage');
        if (! $this->plant) {
            return;
        }
        $location = ctype_digit($this->zoneLocation) ? Location::query()->find((int) $this->zoneLocation) : null;
        $item = $this->wrap(fn () => $editor->addZone($this->plant, $location, $this->zoneLabel !== '' ? $this->zoneLabel : null), '', 'zoneLabel');
        if ($item) {
            $this->zoneLocation = $this->zoneLabel = '';
            $this->select($item->id);
        }
    }

    public function move(int $id, float $x, float $z, LayoutEditor $editor): void
    {
        $this->authorize('assets.manage');
        $editor->move($this->findItem($id), $x, $z);
        if ($this->selected === $id) {
            $this->select($id);
        }
    }

    public function rotate(int $id, int $delta, LayoutEditor $editor): void
    {
        $this->authorize('assets.manage');
        $item = $this->findItem($id);
        $editor->update($item, ['rotation' => $item->rotation + $delta]);
        $this->select($id);
    }

    public function select(?int $id): void
    {
        $this->resetErrorBag();
        $item = $id ? $this->findItem($id) : null;
        $this->selected = $item?->id;
        $this->item = $item ? [
            'w' => $this->num($item->w),
            'd' => $this->num($item->d),
            'h' => $item->h !== null ? $this->num($item->h) : '',
            'rotation' => (string) $item->rotation,
            'label' => (string) ($item->label ?? ''),
        ] : ['w' => '', 'd' => '', 'h' => '', 'rotation' => '0', 'label' => ''];
    }

    public function saveItem(LayoutEditor $editor): void
    {
        $this->authorize('assets.manage');
        if (! $this->selected) {
            return;
        }
        $this->validate([
            'item.w' => ['required', 'numeric'],
            'item.d' => ['required', 'numeric'],
            'item.h' => ['nullable', 'numeric'],
            'item.rotation' => ['required', 'integer'],
            'item.label' => ['nullable', 'string', 'max:80'],
        ], attributes: ['item.w' => __('plant.editor.w'), 'item.d' => __('plant.editor.d'), 'item.h' => __('plant.editor.h'), 'item.rotation' => __('plant.editor.rotation')]);
        $item = $this->findItem($this->selected);
        if ($this->wrap(fn () => $editor->update($item, $this->item), 'item.')) {
            $this->select($item->id);
        }
    }

    public function removeItem(int $id, LayoutEditor $editor): void
    {
        $this->authorize('assets.manage');
        $editor->remove($this->findItem($id));
        if ($this->selected === $id) {
            $this->select(null);
        }
        $this->dispatch('toast', message: __('plant.editor.removed'));
    }

    private function findItem(int $id): LayoutItem
    {
        abort_unless($this->plant !== null, 404);

        return $this->plant->items()->with(['layout', 'asset', 'location'])->findOrFail($id);
    }

    /** @return Builder<Asset> */
    private function unplaced(): Builder
    {
        return $this->plant->placeableAssets()->with('category')->whereDoesntHave('plantItem')->orderBy('code');
    }

    /**
     * Esegue un'azione dell'editor riportando gli errori di validazione sui campi del modulo.
     *
     * @template T
     *
     * @param  callable(): T  $action
     * @return T|null
     */
    private function wrap(callable $action, string $prefix, ?string $field = null): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($field ?? $prefix.$key, $messages[0]);
            }

            return null;
        }
    }

    private function num(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    public function render(): View
    {
        $items = $this->plant ? $this->plant->items()->with(['asset.category', 'location'])->get() : collect();
        $used = $items->pluck('location_id')->filter()->all();
        $labels = Location::labels();
        $zoneLocations = [];
        if ($this->plant) {
            $branch = $this->plant->location ? $this->plant->location->branchIds() : array_keys($labels);
            foreach ($branch as $id) {
                if ($id !== $this->plant->location_id && ! in_array($id, $used, true) && isset($labels[$id])) {
                    $zoneLocations[$id] = $labels[$id];
                }
            }
        }

        return view('livewire.plant.editor', [
            'items' => $items,
            'data' => $this->plant ? PlantData::for($this->plant, auth()->user()) : null,
            'unplaced' => $this->plant ? $this->unplaced()->limit(500)->get() : collect(),
            'unplacedCount' => $this->plant ? $this->unplaced()->count() : 0,
            'zoneLocations' => $zoneLocations,
            'locations' => $labels,
            'selectedItem' => $this->selected ? $items->firstWhere('id', $this->selected) : null,
            'heights' => Shapes::SIZES,
        ])->title($this->plant ? __('plant.editor.title') : __('plant.new'))
            ->layoutData(['heading' => $this->plant ? __('plant.editor.title') : __('plant.new')]);
    }
}
