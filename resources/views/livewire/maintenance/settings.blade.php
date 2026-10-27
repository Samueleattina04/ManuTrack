<div class="mx-auto flex max-w-5xl flex-col gap-5">
    <a href="{{ route('work-orders.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('maintenance.order.title') }}</a>

    {{-- Checklist --}}
    <section class="card overflow-hidden" aria-labelledby="s-tpl">
        <div class="flex items-center gap-2 border-b border-line-soft px-4 py-3">
            <h2 id="s-tpl" class="flex-1 text-base font-bold">{{ __('maintenance.settings.templates') }}</h2>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="editTemplate"><x-icon name="plus" class="!w-4 !h-4" />{{ __('maintenance.settings.new_template') }}</button>
        </div>
        @if($editingTemplate !== null)
            <form wire:submit="saveTemplate" class="flex flex-col gap-3 border-b border-line-soft p-4">
                <x-form.input name="templateName" :label="__('maintenance.settings.template_name')" wire:model="templateName" :placeholder="__('maintenance.settings.template_name_placeholder')" />
                <div class="field">
                    <label for="tpl-items" class="field-label">{{ __('maintenance.settings.template_items') }}</label>
                    <textarea id="tpl-items" wire:model="templateItems" rows="8" class="input py-2.5 font-mono text-sm" placeholder="{{ __('maintenance.settings.template_items_placeholder') }}"></textarea>
                    <span class="field-hint">{{ __('maintenance.settings.template_items_hint') }}</span>
                    @error('templateItems')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('app.save') }}</button>
                    <button type="button" class="btn btn-ghost" wire:click="$set('editingTemplate', null)">{{ __('app.cancel') }}</button>
                    @if($editingTemplate)<button type="button" class="btn btn-ghost ml-auto text-ko" wire:click="deleteTemplate({{ $editingTemplate }})" wire:confirm="{{ __('maintenance.settings.template_delete_confirm') }}"><x-icon name="trash" />{{ __('app.delete') }}</button>@endif
                </div>
            </form>
        @endif
        <ul>
            @forelse($templates as $t)
                <li class="flex items-center gap-3 border-b border-line-soft px-4 py-2.5 last:border-0">
                    <x-icon name="check-circle" class="text-muted" />
                    <span class="flex-1 font-medium">{{ $t->name }}</span>
                    <span class="text-sm text-muted">{{ trans_choice('maintenance.settings.items_count', $t->items_count, ['count' => $t->items_count]) }}</span>
                    <button type="button" class="btn btn-ghost btn-icon btn-sm" wire:click="editTemplate({{ $t->id }})" aria-label="{{ __('app.edit') }} {{ $t->name }}"><x-icon name="pencil" class="!w-4 !h-4" /></button>
                </li>
            @empty
                <li class="px-4 py-4 text-sm text-muted">{{ __('maintenance.settings.no_templates') }}</li>
            @endforelse
        </ul>
    </section>

    {{-- Codici guasto --}}
    <section class="card overflow-hidden" aria-labelledby="s-codes">
        <h2 id="s-codes" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('maintenance.settings.codes') }}</h2>
        <form wire:submit="addCode" class="grid gap-3 border-b border-line-soft p-4 sm:grid-cols-[10rem_8rem_1fr_auto] sm:items-end">
            <div class="field"><label for="fc-kind" class="field-label">{{ __('maintenance.settings.kind') }}</label><select id="fc-kind" wire:model="code.kind" class="input">@foreach($kinds as $k)<option value="{{ $k->value }}">{{ $k->label() }}</option>@endforeach</select></div>
            <x-form.input name="code.code" :label="__('maintenance.settings.code')" wire:model="code.code" class="font-mono" placeholder="P01" />
            <x-form.input name="code.label" :label="__('maintenance.settings.code_label')" wire:model="code.label" :placeholder="__('maintenance.settings.code_label_placeholder')" />
            <button type="submit" class="btn btn-secondary"><x-icon name="plus" />{{ __('maintenance.settings.add_code') }}</button>
        </form>
        <div class="grid gap-0 sm:grid-cols-3">
            @foreach($kinds as $k)
                <div class="border-b border-line-soft p-4 sm:border-b-0 sm:border-r last:border-0">
                    <h3 class="mb-2 font-semibold">{{ $k->label() }}</h3>
                    <ul class="flex flex-col gap-1 text-sm">
                        @forelse($codes[$k->value] ?? [] as $c)
                            <li class="flex items-center gap-2" wire:key="fc-{{ $c->id }}">
                                <span @class(['flex-1', 'text-muted line-through' => ! $c->is_active])><span class="font-mono font-semibold">{{ $c->code }}</span> {{ $c->label }}</span>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="toggleCode({{ $c->id }})">{{ $c->is_active ? __('maintenance.settings.disable') : __('maintenance.settings.enable') }}</button>
                            </li>
                        @empty
                            <li class="text-muted">—</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Costo orario --}}
    <section class="card p-4" aria-labelledby="s-rate">
        <h2 id="s-rate" class="mb-1 text-base font-bold">{{ __('maintenance.settings.rate') }}</h2>
        <p class="mb-3 text-sm text-fg-2">{{ __('maintenance.settings.rate_hint') }}</p>
        <form wire:submit="saveRate" class="flex max-w-sm items-start gap-2">
            <div class="field flex-1"><label for="rate" class="sr-only">{{ __('maintenance.settings.rate') }}</label>
                <div class="relative"><input id="rate" type="text" inputmode="decimal" wire:model="hourlyRate" class="input pr-14"><span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm text-muted">€/h</span></div>
                @error('hourlyRate')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
        </form>
    </section>
</div>
