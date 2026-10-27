<div class="mx-auto flex max-w-4xl flex-col gap-5">
    <a href="{{ route('inventory.parts.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('inventory.title') }}</a>
    <div class="flex flex-wrap items-center gap-3">
        <p class="flex-1 text-sm text-fg-2">{{ __('inventory.warehouses.intro') }}</p>
        <button type="button" class="btn btn-primary" wire:click="edit"><x-icon name="plus" />{{ __('inventory.warehouses.new') }}</button>
    </div>

    @if($editing !== null)
        <form wire:submit="save" class="card flex flex-col gap-4 p-5">
            <div class="grid gap-4 sm:grid-cols-[1fr_10rem]">
                <x-form.input name="form.name" :label="__('inventory.field.name')" wire:model="form.name" :placeholder="__('inventory.warehouses.name_placeholder')" />
                <x-form.input name="form.code" :label="__('inventory.field.code')" wire:model="form.code" class="font-mono" />
            </div>
            <div class="field">
                <label for="w-loc" class="field-label">{{ __('assets.field.location_id') }}</label>
                <select id="w-loc" wire:model="form.location_id" class="input"><option value="">—</option>@foreach($locations as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
            </div>
            <x-form.input name="form.description" :label="__('inventory.field.description')" wire:model="form.description" />
            @if($editing)
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.is_active" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('inventory.field.is_active') }}</label>
                @error('form.is_active')<span class="field-error">{{ $message }}</span>@enderror
            @endif
            <div class="flex gap-2"><button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('app.save') }}</button><button type="button" class="btn btn-ghost" wire:click="$set('editing', null)">{{ __('app.cancel') }}</button></div>
        </form>
    @endif

    <section class="card overflow-hidden" aria-label="{{ __('inventory.warehouses.title') }}">
        <ul>
            @forelse($warehouses as $w)
                <li class="flex items-center gap-3 border-b border-line-soft px-4 py-3 last:border-0" wire:key="w-{{ $w->id }}">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[10px] bg-surface-2 text-fg-2"><x-icon name="box" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ $w->name }} @if($w->code)<span class="font-mono text-xs text-muted">{{ $w->code }}</span>@endif @unless($w->is_active)<x-status tone="stop" class="ml-1">{{ __('inventory.part.inactive') }}</x-status>@endunless</div>
                        <div class="truncate text-xs text-muted">{{ $w->location?->fullName() ?? '' }}{{ $w->description ? ' · '.$w->description : '' }}</div>
                    </div>
                    <a href="{{ route('inventory.parts.index', ['magazzino' => $w->id]) }}" class="btn btn-ghost btn-sm num">{{ trans_choice('inventory.warehouses.parts_count', $w->parts_count, ['count' => $w->parts_count]) }}</a>
                    <button type="button" class="btn btn-ghost btn-icon btn-sm" wire:click="edit({{ $w->id }})" aria-label="{{ __('app.edit') }} {{ $w->name }}"><x-icon name="pencil" class="!w-4 !h-4" /></button>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-muted">{{ __('inventory.warehouses.empty') }}</li>
            @endforelse
        </ul>
    </section>
</div>
