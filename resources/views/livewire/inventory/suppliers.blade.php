<div class="mx-auto flex max-w-4xl flex-col gap-5">
    <a href="{{ route('inventory.parts.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('inventory.title') }}</a>
    <div class="flex flex-wrap items-end gap-3">
        <div class="field min-w-[14rem] flex-1">
            <label for="s-search" class="field-label">{{ __('assets.search') }}</label>
            <input id="s-search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="{{ __('inventory.suppliers.search_placeholder') }}">
        </div>
        @if($canManage)<button type="button" class="btn btn-primary" wire:click="edit"><x-icon name="plus" />{{ __('inventory.suppliers.new') }}</button>@endif
    </div>

    @if($editing !== null)
        <form wire:submit="save" class="card flex flex-col gap-4 p-5">
            <x-form.input name="form.name" :label="__('inventory.supplier_field.name')" wire:model="form.name" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="form.contact_name" :label="__('inventory.supplier_field.contact_name')" wire:model="form.contact_name" />
                <x-form.input name="form.email" type="email" :label="__('inventory.supplier_field.email')" wire:model="form.email" />
                <x-form.input name="form.phone" type="tel" :label="__('inventory.supplier_field.phone')" wire:model="form.phone" />
                <x-form.input name="form.website" type="url" :label="__('inventory.supplier_field.website')" wire:model="form.website" placeholder="https://" />
            </div>
            <div class="field"><label for="s-notes" class="field-label">{{ __('inventory.supplier_field.notes') }}</label><textarea id="s-notes" wire:model="form.notes" rows="2" class="input py-2.5"></textarea></div>
            @if($editing)<label class="flex items-center gap-2"><input type="checkbox" wire:model="form.is_active" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('inventory.field.is_active') }}</label>@endif
            <div class="flex gap-2"><button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('app.save') }}</button><button type="button" class="btn btn-ghost" wire:click="$set('editing', null)">{{ __('app.cancel') }}</button></div>
        </form>
    @endif

    <section class="card overflow-hidden" aria-label="{{ __('inventory.suppliers.title') }}">
        <ul>
            @forelse($suppliers as $s)
                <li class="border-b border-line-soft last:border-0" wire:key="s-{{ $s->id }}">
                    <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[10px] bg-surface-2 text-fg-2"><x-icon name="truck" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $s->name }} @unless($s->is_active)<x-status tone="stop" class="ml-1">{{ __('inventory.part.inactive') }}</x-status>@endunless</div>
                            <div class="truncate text-sm text-muted">{{ collect([$s->contact_name, $s->phone, $s->email])->filter()->implode(' · ') ?: '—' }}</div>
                        </div>
                        <span class="flex gap-1">
                            @if($s->email)<a href="mailto:{{ $s->email }}" class="btn btn-ghost btn-icon btn-sm" aria-label="{{ __('inventory.supplier_field.email') }}"><x-icon name="mail" class="!w-4 !h-4" /></a>@endif
                            @if($s->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $s->phone) }}" class="btn btn-ghost btn-icon btn-sm" aria-label="{{ __('inventory.supplier_field.phone') }}"><x-icon name="phone" class="!w-4 !h-4" /></a>@endif
                            <button type="button" class="btn btn-ghost btn-sm num" wire:click="toggle({{ $s->id }})" aria-expanded="{{ $open === $s->id ? 'true' : 'false' }}">{{ trans_choice('inventory.suppliers.parts_count', $s->parts_count, ['count' => $s->parts_count]) }}</button>
                            @if($canManage)<button type="button" class="btn btn-ghost btn-icon btn-sm" wire:click="edit({{ $s->id }})" aria-label="{{ __('app.edit') }} {{ $s->name }}"><x-icon name="pencil" class="!w-4 !h-4" /></button>@endif
                        </span>
                    </div>
                    @if($open === $s->id && $openParts)
                        <ul class="border-t border-line-soft bg-surface-2/40 px-4 py-2 text-sm">
                            @forelse($openParts as $p)
                                <li class="flex items-center gap-2 py-1"><a href="{{ route('inventory.parts.show', $p) }}" class="min-w-0 flex-1 truncate underline"><span class="font-mono">{{ $p->code }}</span> · {{ $p->name }}</a>
                                    @if($p->pivot->supplier_code)<span class="font-mono text-muted">{{ $p->pivot->supplier_code }}</span>@endif
                                    @if($canCosts && $p->pivot->price !== null)<span class="num">€ {{ number_format((float) $p->pivot->price, 2, ',', '.') }}</span>@endif
                                </li>
                            @empty
                                <li class="py-1 text-muted">{{ __('inventory.suppliers.no_parts') }}</li>
                            @endforelse
                        </ul>
                    @endif
                </li>
            @empty
                <li class="px-4 py-8 text-center text-muted">{{ __('inventory.suppliers.empty') }}</li>
            @endforelse
        </ul>
    </section>
</div>
