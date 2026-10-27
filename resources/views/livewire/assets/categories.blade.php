<div class="mx-auto flex max-w-4xl flex-col gap-5">
    <a href="{{ route('assets.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('assets.title') }}</a>

    <div class="flex flex-wrap items-center gap-3">
        <p class="flex-1 text-sm text-fg-2">{{ __('assets.categories.intro') }}</p>
        <button type="button" class="btn btn-primary" wire:click="edit"><x-icon name="plus" />{{ __('assets.categories.new') }}</button>
    </div>

    @if($editing !== null)
        <form wire:submit="save" class="card flex flex-col gap-4 p-5" aria-labelledby="cat-form">
            <div class="flex items-center justify-between gap-3">
                <h2 id="cat-form" class="text-lg font-bold">{{ $editing ? __('assets.categories.edit') : __('assets.categories.new') }}</h2>
                <button type="button" class="btn btn-ghost btn-icon" wire:click="$set('editing', null)" aria-label="{{ __('app.close') }}"><x-icon name="x" /></button>
            </div>
            <x-form.input name="form.name" :label="__('assets.categories.name')" wire:model="form.name" :placeholder="__('assets.categories.name_placeholder')" required />
            <fieldset>
                <legend class="field-label mb-2">{{ __('assets.categories.icon') }}</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach($icons as $icon)
                        <label class="choice !p-2.5" title="{{ $icon }}">
                            <input type="radio" name="icon" value="{{ $icon }}" wire:model="form.icon" class="sr-only">
                            <x-icon :name="$icon" label="{{ $icon }}" />
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-[12rem_1fr]">
                <div class="field">
                    <label for="cat-shape" class="field-label">{{ __('assets.categories.shape') }}</label>
                    <select id="cat-shape" wire:model="form.shape" class="input">
                        @foreach($shapes as $shape)<option value="{{ $shape }}">{{ __('assets.categories.shapes.'.$shape) }}</option>@endforeach
                    </select>
                    <span class="field-hint">{{ __('assets.categories.shape_hint') }}</span>
                </div>
                <x-form.input name="form.description" :label="__('assets.categories.description')" wire:model="form.description" />
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('app.save') }}</button>
                <button type="button" class="btn btn-ghost" wire:click="$set('editing', null)">{{ __('app.cancel') }}</button>
            </div>
        </form>
    @endif

    <section class="card overflow-hidden" aria-label="{{ __('assets.categories.title') }}">
        <ul>
            @forelse($categories as $c)
                <li class="flex items-center gap-3 border-b border-line-soft px-4 py-2.5 last:border-0" wire:key="cat-{{ $c->id }}">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[10px] bg-surface-2 text-fg-2"><x-icon :name="$c->icon" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ $c->name }}</div>
                        @if($c->description)<div class="truncate text-xs text-muted">{{ $c->description }}</div>@endif
                    </div>
                    <a href="{{ route('assets.index', ['categoria' => $c->id]) }}" class="btn btn-ghost btn-sm num">{{ trans_choice('assets.locations.assets_count', $c->assets_count, ['count' => $c->assets_count]) }}</a>
                    <button type="button" class="btn btn-ghost btn-sm btn-icon" wire:click="edit({{ $c->id }})" aria-label="{{ __('app.edit') }} {{ $c->name }}"><x-icon name="pencil" class="!w-4 !h-4" /></button>
                    <button type="button" class="btn btn-ghost btn-sm btn-icon text-ko" wire:click="delete({{ $c->id }})" wire:confirm="{{ trans_choice('assets.categories.delete_confirm', $c->assets_count, ['name' => $c->name, 'count' => $c->assets_count]) }}" aria-label="{{ __('app.delete') }} {{ $c->name }}"><x-icon name="trash" class="!w-4 !h-4" /></button>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-muted">{{ __('assets.categories.empty') }}</li>
            @endforelse
        </ul>
    </section>
</div>
