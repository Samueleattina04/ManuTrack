<?php

namespace App\Livewire\Assets;

use App\Actions\Assets\SaveAsset;
use App\Enums\AssetStatus;
use App\Enums\Criticality;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Nuova macchina o modifica dei dati di una macchina.
 * Lo stato si cambia dalla scheda (così partono gli avvisi), non da qui.
 */
class Form extends Component
{
    public ?Asset $asset = null;

    /** @var array<string, mixed> */
    public array $form = [
        'code' => '', 'name' => '', 'asset_category_id' => '', 'location_id' => '', 'parent_id' => '',
        'serial_number' => '', 'manufacturer' => '', 'model' => '', 'year' => '', 'installed_on' => '',
        'criticality' => 'B', 'status' => 'operativa', 'purchase_cost' => '', 'warranty_until' => '', 'notes' => '',
    ];

    public function mount(?Asset $asset = null): void
    {
        $this->authorize('assets.manage');

        if ($asset?->exists) {
            $this->asset = $asset;
            foreach (array_keys($this->form) as $key) {
                $value = $asset->getAttribute($key);
                $this->form[$key] = match (true) {
                    $value instanceof \BackedEnum => $value->value,
                    $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                    default => (string) ($value ?? ''),
                };
            }
        } else {
            // Scorciatoie: "Aggiungi una macchina qui" dalle ubicazioni o da una macchina padre
            $this->form['location_id'] = (string) request()->integer('ubicazione') ?: '';
            if ($parent = Asset::query()->find(request()->integer('padre'))) {
                $this->form['parent_id'] = (string) $parent->id;
                $this->form['location_id'] = (string) ($parent->location_id ?? '');
            }
        }
    }

    public function save(SaveAsset $save): RedirectResponse|Redirector|null
    {
        $this->authorize('assets.manage');

        $input = array_map(fn ($v) => $v === '' ? null : $v, $this->form);
        if ($this->asset) {
            $input['status'] = $this->asset->status->value;
        }

        try {
            $asset = $save->handle($input, $this->asset);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('form.'.$field, $messages[0]);
            }

            return null;
        }

        session()->flash('status', __($this->asset ? 'assets.saved' : 'assets.created', ['code' => $asset->code]));

        return redirect()->route('assets.show', $asset);
    }

    public function render(): View
    {
        $title = $this->asset ? __('assets.edit_title', ['code' => $this->asset->code]) : __('assets.new');
        $excluded = $this->asset ? [$this->asset->id, ...$this->asset->descendantIds()] : [];

        return view('livewire.assets.form', [
            'locations' => Location::labels(),
            'categories' => AssetCategory::query()->orderBy('name')->get(['id', 'name', 'icon']),
            'parents' => Asset::query()->whereNotIn('id', $excluded)->orderBy('code')->limit(2000)->get(['id', 'code', 'name']),
            'criticalities' => Criticality::cases(),
            'statuses' => AssetStatus::cases(),
            'canSeeCosts' => auth()->user()->can('work_orders.view_costs'),
        ])->title($title)->layoutData(['heading' => $title]);
    }
}
