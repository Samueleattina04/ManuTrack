<div class="mx-auto flex max-w-4xl flex-col gap-5">
    <a href="{{ route('assets.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('assets.title') }}</a>

    <div class="flex flex-wrap items-center gap-3">
        <p class="flex-1 text-sm text-fg-2">{{ __('assets.locations.intro') }}</p>
        @if($canManage)<button type="button" class="btn btn-primary" wire:click="create"><x-icon name="plus" />{{ __('assets.locations.new_site') }}</button>@endif
    </div>

    @if($editing !== null)
        <form wire:submit="save" class="card flex flex-col gap-4 p-5" aria-labelledby="loc-form">
            <div class="flex items-center justify-between gap-3">
                <h2 id="loc-form" class="text-lg font-bold">{{ $editingLocation ? __('assets.locations.edit', ['name' => $editingLocation->name]) : __('assets.locations.new') }}</h2>
                <button type="button" class="btn btn-ghost btn-icon" wire:click="$set('editing', null)" aria-label="{{ __('app.close') }}"><x-icon name="x" /></button>
            </div>
            @if($parents !== [] || ! $editingLocation)
                <div class="field">
                    <label for="loc-parent" class="field-label">{{ __('assets.locations.parent') }}</label>
                    <select id="loc-parent" wire:model.live="form.parent_id" class="input">
                        @unless($editingLocation)<option value="">{{ __('assets.locations.no_parent') }}</option>@endunless
                        @foreach($parents as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                    @php $depth = $form['parent_id'] === '' ? 0 : (\App\Models\Location::query()->find((int) $form['parent_id'])?->depth ?? -1) + 1; @endphp
                    <span class="field-hint">{{ __('assets.locations.will_be', ['type' => \App\Enums\LocationType::forDepth(max(0, $depth))->label()]) }}</span>
                    @error('form.parent_id')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            @endif
            <div class="grid gap-4 sm:grid-cols-[1fr_10rem]">
                <x-form.input name="form.name" :label="__('assets.locations.name')" wire:model="form.name" :placeholder="__('assets.locations.name_placeholder')" required />
                <x-form.input name="form.code" :label="__('assets.locations.code')" wire:model="form.code" class="font-mono" />
            </div>
            <x-form.input name="form.description" :label="__('assets.locations.description')" wire:model="form.description" />
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('app.save') }}</button>
                <button type="button" class="btn btn-ghost" wire:click="$set('editing', null)">{{ __('app.cancel') }}</button>
                @if($editingLocation)
                    <button type="button" class="btn btn-ghost ml-auto text-ko" wire:click="delete({{ $editingLocation->id }})" wire:confirm="{{ __('assets.locations.delete_confirm', ['name' => $editingLocation->name]) }}"><x-icon name="trash" />{{ __('app.delete') }}</button>
                @endif
            </div>
        </form>
    @endif

    @if($tree->isEmpty())
        <section class="card flex flex-col items-center gap-3 px-6 py-10 text-center">
            <span class="grid h-14 w-14 place-items-center rounded-2xl bg-surface-2 text-accent-fg"><x-icon name="map-pin" class="!w-7 !h-7" /></span>
            <h2 class="text-lg font-bold">{{ __('assets.locations.empty_title') }}</h2>
            <p class="max-w-md text-sm text-fg-2">{{ __('assets.locations.empty_text') }}</p>
        </section>
    @else
        <section class="card p-3 sm:p-4" aria-label="{{ __('assets.locations.title') }}">
            <ul class="flex flex-col gap-1">
                @foreach($tree as $loc)
                    @include('livewire.assets.partials.location-node', ['loc' => $loc])
                @endforeach
            </ul>
        </section>
    @endif
</div>
