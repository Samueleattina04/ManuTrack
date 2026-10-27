<form wire:submit="save" class="mx-auto flex max-w-3xl flex-col gap-5" novalidate>
    <a href="{{ $part ? route('inventory.parts.show', $part) : route('inventory.parts.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ $part ? $part->code : __('inventory.title') }}</a>

    @if($errors->any())<div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ __('assets.form.has_errors') }}</span></div>@endif

    <section class="card flex flex-col gap-4 p-5" aria-labelledby="pf-id">
        <h2 id="pf-id" class="text-lg font-bold">{{ __('assets.form.identity') }}</h2>
        <div class="grid gap-4 sm:grid-cols-[minmax(0,14rem)_1fr]">
            <x-form.input name="form.code" :label="__('inventory.field.code')" wire:model="form.code" class="font-mono" :hint="__('inventory.part.code_hint')" autocomplete="off" />
            <x-form.input name="form.name" :label="__('inventory.field.name')" wire:model="form.name" />
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="field">
                <label for="pf-cat" class="field-label">{{ __('inventory.field.category') }}</label>
                <input id="pf-cat" list="pf-cats" wire:model="form.category" class="input" maxlength="80" placeholder="{{ __('inventory.part.category_placeholder') }}">
                <datalist id="pf-cats">@foreach($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
            </div>
            <div class="field">
                <label for="pf-unit" class="field-label">{{ __('inventory.field.unit') }}</label>
                <input id="pf-unit" list="pf-units" wire:model="form.unit" class="input" maxlength="10">
                <datalist id="pf-units">@foreach($units as $u)<option value="{{ $u }}">@endforeach</datalist>
            </div>
            @if($part)
                <label class="flex items-end gap-2 pb-2.5"><input type="checkbox" wire:model="form.is_active" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('inventory.field.is_active') }}</label>
            @endif
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="form.manufacturer" :label="__('inventory.field.manufacturer')" wire:model="form.manufacturer" />
            <x-form.input name="form.manufacturer_code" :label="__('inventory.field.manufacturer_code')" wire:model="form.manufacturer_code" class="font-mono" />
        </div>
        <div class="field">
            <label for="pf-desc" class="field-label">{{ __('inventory.field.description') }}</label>
            <textarea id="pf-desc" wire:model="form.description" rows="2" class="input py-2.5"></textarea>
        </div>
    </section>

    <section class="card flex flex-col gap-4 p-5" aria-labelledby="pf-min">
        <div>
            <h2 id="pf-min" class="text-lg font-bold">{{ __('inventory.part.minimum_title') }}</h2>
            <p class="text-sm text-fg-2">{{ __('inventory.part.minimum_intro') }}</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="form.min_stock" :label="__('inventory.field.min_stock')" wire:model="form.min_stock" inputmode="decimal" />
            <x-form.input name="form.reorder_qty" :label="__('inventory.field.reorder_qty')" wire:model="form.reorder_qty" inputmode="decimal" :hint="__('inventory.part.reorder_hint')" />
        </div>
    </section>

    @unless($part)
        <section class="card flex flex-col gap-4 p-5" aria-labelledby="pf-init">
            <div>
                <h2 id="pf-init" class="text-lg font-bold">{{ __('inventory.part.initial_title') }}</h2>
                <p class="text-sm text-fg-2">{{ __('inventory.part.initial_intro') }}</p>
            </div>
            @if($warehouses->isEmpty())
                <p class="text-sm text-warn">{!! __('inventory.part.no_warehouses', ['url' => e(route('inventory.warehouses'))]) !!}</p>
            @else
                <div class="grid gap-4 sm:grid-cols-4">
                    <div class="field">
                        <label for="pf-wh" class="field-label">{{ __('inventory.field.warehouse') }}</label>
                        <select id="pf-wh" wire:model="initial.warehouse_id" class="input">@foreach($warehouses as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                        @error('initial.warehouse_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <x-form.input name="initial.bin" :label="__('inventory.field.bin')" wire:model="initial.bin" class="font-mono" placeholder="A-03-2" />
                    <x-form.input name="initial.quantity" :label="__('inventory.field.quantity')" wire:model="initial.quantity" inputmode="decimal" />
                    @if($canCosts)<x-form.input name="initial.unit_cost" :label="__('inventory.field.unit_cost_eur')" wire:model="initial.unit_cost" inputmode="decimal" />@endif
                </div>
            @endif
        </section>
    @endunless

    <div class="flex justify-end gap-2">
        <a href="{{ $part ? route('inventory.parts.show', $part) : route('inventory.parts.index') }}" class="btn btn-secondary">{{ __('app.cancel') }}</a>
        <button type="submit" class="btn btn-primary btn-xl" wire:loading.attr="disabled"><x-icon name="check" />{{ $part ? __('assets.form.save') : __('inventory.part.create') }}</button>
    </div>
</form>
