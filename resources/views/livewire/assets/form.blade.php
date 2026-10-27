@php
    $err = fn ($f) => $errors->first('form.'.$f);
@endphp
<form wire:submit="save" class="mx-auto flex max-w-4xl flex-col gap-5" novalidate>
    <a href="{{ $asset ? route('assets.show', $asset) : route('assets.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ $asset ? $asset->code : __('assets.title') }}</a>

    @if($errors->any())
        <div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ __('assets.form.has_errors') }}</span></div>
    @endif

    {{-- Identificazione --}}
    <section class="card flex flex-col gap-4 p-5" aria-labelledby="f-id">
        <h2 id="f-id" class="text-lg font-bold">{{ __('assets.form.identity') }}</h2>
        <div class="grid gap-4 sm:grid-cols-[minmax(0,14rem)_1fr]">
            <x-form.input name="form.code" :label="__('assets.field.code')" :hint="__('assets.form.code_hint')" wire:model="form.code" autocomplete="off" autocapitalize="characters" class="font-mono" required />
            <x-form.input name="form.name" :label="__('assets.field.name')" wire:model="form.name" required />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="field">
                <label for="f-loc" class="field-label">{{ __('assets.field.location_id') }}</label>
                <select id="f-loc" wire:model="form.location_id" class="input">
                    <option value="">{{ __('assets.form.no_location') }}</option>
                    @foreach($locations as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                </select>
                @if($locations === [])<span class="field-hint">{!! __('assets.form.no_locations_yet', ['url' => e(route('assets.locations'))]) !!}</span>@endif
                @if($err('location_id'))<span class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $err('location_id') }}</span>@endif
            </div>
            <div class="field">
                <label for="f-cat" class="field-label">{{ __('assets.field.asset_category_id') }}</label>
                <select id="f-cat" wire:model="form.asset_category_id" class="input">
                    <option value="">{{ __('assets.no_category') }}</option>
                    @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="field sm:col-span-2">
                <label for="f-parent" class="field-label">{{ __('assets.field.parent_id') }}</label>
                <select id="f-parent" wire:model="form.parent_id" class="input">
                    <option value="">{{ __('assets.form.no_parent') }}</option>
                    @foreach($parents as $p)<option value="{{ $p->id }}">{{ $p->code }} · {{ $p->name }}</option>@endforeach
                </select>
                <span class="field-hint">{{ __('assets.form.parent_hint') }}</span>
                @if($err('parent_id'))<span class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $err('parent_id') }}</span>@endif
            </div>
        </div>
    </section>

    {{-- Criticità --}}
    <section class="card flex flex-col gap-4 p-5" aria-labelledby="f-crit">
        <div>
            <h2 id="f-crit" class="text-lg font-bold">{{ __('assets.field.criticality') }}</h2>
            <p class="text-sm text-fg-2">{{ __('assets.form.criticality_intro') }}</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-labelledby="f-crit">
            @foreach($criticalities as $c)
                <label class="choice">
                    <input type="radio" name="criticality" value="{{ $c->value }}" wire:model="form.criticality" class="sr-only">
                    <x-criticality :value="$c" class="mt-0.5" />
                    <span><span class="block font-semibold">{{ $c->label() }}</span><span class="block text-sm text-muted">{{ __('assets.criticality_help.'.$c->value) }}</span></span>
                </label>
            @endforeach
        </div>
        @unless($asset)
            <div class="field max-w-xs">
                <label for="f-status" class="field-label">{{ __('assets.form.initial_status') }}</label>
                <select id="f-status" wire:model="form.status" class="input">
                    @foreach($statuses as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                </select>
            </div>
        @endunless
    </section>

    {{-- Dati tecnici --}}
    <section class="card flex flex-col gap-4 p-5" aria-labelledby="f-tech">
        <h2 id="f-tech" class="text-lg font-bold">{{ __('assets.form.technical') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="form.manufacturer" :label="__('assets.field.manufacturer')" wire:model="form.manufacturer" />
            <x-form.input name="form.model" :label="__('assets.field.model')" wire:model="form.model" />
            <x-form.input name="form.serial_number" :label="__('assets.field.serial_number')" wire:model="form.serial_number" class="font-mono" />
            <div class="grid grid-cols-2 gap-4">
                <x-form.input name="form.year" :label="__('assets.field.year')" wire:model="form.year" inputmode="numeric" maxlength="4" />
                <x-form.input name="form.installed_on" type="date" :label="__('assets.field.installed_on')" wire:model="form.installed_on" />
            </div>
            <x-form.input name="form.warranty_until" type="date" :label="__('assets.field.warranty_until')" wire:model="form.warranty_until" />
            @if($canSeeCosts)
                <x-form.input name="form.purchase_cost" :label="__('assets.field.purchase_cost')" wire:model="form.purchase_cost" inputmode="decimal" :hint="__('assets.form.cost_hint')" />
            @endif
        </div>
        <div class="field">
            <label for="f-notes" class="field-label">{{ __('assets.field.notes') }}</label>
            <textarea id="f-notes" wire:model="form.notes" rows="4" class="input py-2.5"></textarea>
        </div>
    </section>

    <div class="sticky bottom-[calc(var(--mt-bottomnav-h)+var(--safe-bottom)+0.75rem)] z-10 flex justify-end gap-2 lg:bottom-4">
        <a href="{{ $asset ? route('assets.show', $asset) : route('assets.index') }}" class="btn btn-secondary">{{ __('app.cancel') }}</a>
        <button type="submit" class="btn btn-primary btn-xl" wire:loading.attr="disabled"><x-icon name="check" />{{ $asset ? __('assets.form.save') : __('assets.form.create') }}</button>
    </div>
</form>
