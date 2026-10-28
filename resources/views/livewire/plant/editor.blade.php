<div class="mx-auto flex max-w-7xl flex-col gap-4">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ $plant ? route('plant.index', ['pianta' => $plant->id]) : route('plant.index') }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" class="!h-4 !w-4" />{{ __('plant.editor.back') }}</a>
    </div>

    <div @class(['grid gap-4', 'lg:grid-cols-[minmax(0,1fr)_21rem]' => $plant])>
        @if($plant && $data)
            {{-- Pianta modificabile --}}
            <section class="card flex flex-col overflow-hidden" aria-labelledby="e-plan" x-data="plantEditor()">
                <div class="flex flex-wrap items-center gap-2 border-b border-line-soft px-4 py-3">
                    <h2 id="e-plan" class="flex-1 text-base font-bold">{{ $plant->name }} <span class="text-sm font-normal text-muted">· {{ __('plant.size', ['w' => $data['layout']['width'], 'd' => $data['layout']['depth']]) }}</span></h2>
                </div>
                <p class="px-4 pt-3 text-sm text-muted">{{ __('plant.editor.help') }}</p>
                <div class="h-[58vh] min-h-[320px] p-2 sm:p-3 lg:h-[66vh]">
                    <x-plant-plan :data="$data" :editable="true" :selected="$selected" />
                </div>
            </section>
        @endif

        <div class="flex flex-col gap-4">
            {{-- Elemento scelto --}}
            @if($plant)
                <section class="card flex flex-col gap-3 p-4" aria-labelledby="e-sel">
                    <h2 id="e-sel" class="text-base font-bold">{{ __('plant.editor.selected') }}</h2>
                    @if($selectedItem)
                        <p class="font-semibold">
                            @if($selectedItem->asset)
                                <span class="font-mono">{{ $selectedItem->asset->code }}</span> {{ $selectedItem->asset->name }}
                            @else
                                {{ $selectedItem->name() }}
                            @endif
                        </p>
                        <form wire:submit="saveItem" class="grid grid-cols-2 gap-3">
                            <x-form.input name="item.w" :label="__('plant.editor.w')" wire:model="item.w" inputmode="decimal" />
                            <x-form.input name="item.d" :label="__('plant.editor.d')" wire:model="item.d" inputmode="decimal" />
                            @if($selectedItem->asset)
                                <x-form.input name="item.h" :label="__('plant.editor.h')" wire:model="item.h" inputmode="decimal" :hint="__('plant.editor.h_hint')" />
                            @else
                                <div class="col-span-2"><x-form.input name="item.label" :label="__('plant.editor.label')" wire:model="item.label" /></div>
                            @endif
                            <x-form.input name="item.rotation" :label="__('plant.editor.rotation')" wire:model="item.rotation" inputmode="numeric" />
                            <div class="col-span-2 flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary btn-sm"><x-icon name="check" class="!h-4 !w-4" />{{ __('plant.editor.apply') }}</button>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="rotate({{ $selectedItem->id }}, 90)"><x-icon name="refresh" class="!h-4 !w-4" />{{ __('plant.editor.rotate') }}</button>
                                <button type="button" class="btn btn-ghost btn-sm text-ko" wire:click="removeItem({{ $selectedItem->id }})"><x-icon name="trash" class="!h-4 !w-4" />{{ __('plant.editor.remove') }}</button>
                            </div>
                        </form>
                        @if($selectedItem->asset)<p class="text-xs text-muted">{{ __('plant.editor.shape_hint') }}</p>@endif
                    @else
                        <p class="text-sm text-muted">{{ __('plant.editor.nothing_selected') }}</p>
                    @endif
                </section>

                {{-- Aggiungi macchine --}}
                <section class="card flex flex-col gap-3 p-4" aria-labelledby="e-add">
                    <h2 id="e-add" class="text-base font-bold">{{ __('plant.editor.add_asset') }}</h2>
                    @if($unplacedCount === 0)
                        <p class="text-sm text-muted">{{ __('plant.editor.none_left') }}</p>
                    @else
                        <form wire:submit="addAssetToLayout" class="flex flex-col gap-2">
                            <div class="field">
                                <label for="e-asset" class="field-label">{{ __('plant.editor.choose_asset') }}</label>
                                <select id="e-asset" wire:model="addAsset" class="input" @error('addAsset') aria-invalid="true" @enderror>
                                    <option value="">—</option>
                                    @foreach($unplaced as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>@endforeach
                                </select>
                                @error('addAsset')<span class="field-error"><x-icon name="alert-circle" class="!h-4 !w-4" />{{ $message }}</span>@enderror
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary btn-sm"><x-icon name="plus" class="!h-4 !w-4" />{{ __('plant.editor.add') }}</button>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="addAllAssets">{{ __('plant.editor.add_all', ['count' => min(200, $unplacedCount)]) }}</button>
                            </div>
                        </form>
                    @endif
                </section>

                {{-- Aggiungi zone --}}
                <section class="card flex flex-col gap-3 p-4" aria-labelledby="e-zone">
                    <h2 id="e-zone" class="text-base font-bold">{{ __('plant.editor.add_zone') }}</h2>
                    <form wire:submit="addZone" class="flex flex-col gap-2">
                        <div class="field">
                            <label for="e-zone-loc" class="field-label">{{ __('plant.editor.zone_location') }}</label>
                            <select id="e-zone-loc" wire:model="zoneLocation" class="input">
                                <option value="">{{ __('plant.editor.zone_none') }}</option>
                                @foreach($zoneLocations as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <x-form.input name="zoneLabel" :label="__('plant.editor.zone_label')" wire:model="zoneLabel" maxlength="80" />
                        <button type="submit" class="btn btn-secondary btn-sm self-start"><x-icon name="plus" class="!h-4 !w-4" />{{ __('plant.editor.add') }}</button>
                    </form>
                </section>
            @endif

            {{-- Pianta: nome, ubicazione, misure --}}
            <section @class(['card flex flex-col gap-3 p-4', 'max-w-2xl' => ! $plant]) aria-labelledby="e-layout">
                <h2 id="e-layout" class="text-base font-bold">{{ $plant ? __('plant.edit') : __('plant.new') }}</h2>
                <form wire:submit="saveLayout" class="grid grid-cols-2 gap-3">
                    <div class="col-span-2"><x-form.input name="form.name" :label="__('plant.form.name')" :hint="__('plant.form.name_hint')" wire:model="form.name" maxlength="80" required /></div>
                    <div class="field col-span-2">
                        <label for="e-loc" class="field-label">{{ __('plant.form.location') }}</label>
                        <select id="e-loc" wire:model="form.location_id" class="input" aria-describedby="e-loc-hint">
                            <option value="">{{ __('plant.form.all_locations') }}</option>
                            @foreach($locations as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                        <span id="e-loc-hint" class="field-hint">{{ __('plant.form.location_hint') }}</span>
                    </div>
                    <x-form.input name="form.width" :label="__('plant.form.width')" wire:model="form.width" inputmode="decimal" required />
                    <x-form.input name="form.depth" :label="__('plant.form.depth')" wire:model="form.depth" inputmode="decimal" required />
                    <p class="col-span-2 text-xs text-muted">{{ __('plant.form.size_hint') }}</p>
                    <div class="col-span-2 flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary"><x-icon name="check" />{{ $plant ? __('plant.form.save') : __('plant.form.create') }}</button>
                        @if($plant)
                            <button type="button" class="btn btn-ghost text-ko" wire:click="deleteLayout" wire:confirm="{{ __('plant.form.delete_confirm', ['name' => $plant->name]) }}"><x-icon name="trash" />{{ __('plant.form.delete') }}</button>
                        @endif
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>
