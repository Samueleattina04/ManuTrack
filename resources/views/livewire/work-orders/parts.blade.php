@php $q = fn ($v) => \App\Models\Part::formatQty($v); @endphp
<section class="card overflow-hidden" aria-labelledby="w-parts">
    <div class="flex flex-wrap items-center gap-2 border-b border-line-soft px-4 py-3">
        <h2 id="w-parts" class="flex-1 text-base font-bold">{{ __('inventory.order.title') }}</h2>
        @if($canMove && ! $adding)
            <button type="button" class="btn btn-secondary btn-sm" wire:click="open"><x-icon name="box" class="!w-4 !h-4" />{{ __('inventory.order.issue') }}</button>
        @endif
    </div>

    @if($adding)
        <div class="flex flex-col gap-3 border-b border-line-soft bg-surface-2 p-4">
            @if(! $chosen)
                <label for="op-search" class="field-label">{{ __('inventory.order.search') }}</label>
                <input id="op-search" type="search" wire:model.live.debounce.250ms="search" class="input" placeholder="{{ __('inventory.part.search_placeholder') }}" autocomplete="off">
                @if($fromBom && $suggestions->isNotEmpty())<p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('inventory.order.from_bom') }}</p>@endif
                <ul class="flex flex-col gap-1">
                    @foreach($suggestions as $s)
                        <li wire:key="sg-{{ $s->id }}">
                            <button type="button" wire:click="choose({{ $s->id }})" class="flex w-full items-center gap-3 rounded-[var(--radius-md)] border border-line bg-surface px-3 py-2.5 text-left hover:border-accent">
                                <span class="min-w-0 flex-1"><span class="font-mono text-xs text-muted">{{ $s->code }}</span> <span class="font-medium">{{ $s->name }}</span></span>
                                <span @class(['num text-sm', 'text-ko' => (float) $s->stock_total <= 0, 'text-fg-2' => (float) $s->stock_total > 0])>{{ $q($s->stock_total) }} {{ $s->unit }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                @if(! $fromBom && $suggestions->isEmpty() && trim($search) !== '')<p class="text-sm text-muted">{{ __('inventory.part.none_found') }}</p>@endif
                @if($fromBom && $suggestions->isEmpty())<p class="text-sm text-muted">{{ __('inventory.order.search_hint') }}</p>@endif
            @else
                <div class="flex items-center gap-2">
                    <span class="min-w-0 flex-1"><span class="font-mono text-xs text-muted">{{ $chosen->code }}</span> <span class="font-semibold">{{ $chosen->name }}</span></span>
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('partId', null)">{{ __('inventory.order.change') }}</button>
                </div>
                <form wire:submit="issue" class="grid gap-3 sm:grid-cols-[1fr_8rem_auto] sm:items-end">
                    <div class="field">
                        <label for="op-wh" class="field-label">{{ __('inventory.field.warehouse') }}</label>
                        <select id="op-wh" wire:model="warehouseId" class="input">
                            <option value="">—</option>
                            @foreach($levels as $l)<option value="{{ $l->warehouse_id }}" @disabled((float) $l->quantity <= 0)>{{ $l->warehouse->name }} · {{ $q($l->quantity) }} {{ $chosen->unit }}@if($l->bin) · {{ $l->bin }}@endif</option>@endforeach
                        </select>
                        @error('warehouseId')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="op-qty" class="field-label">{{ __('inventory.field.quantity') }} ({{ $chosen->unit }})</label>
                        <input id="op-qty" type="text" inputmode="decimal" wire:model="quantity" class="input num">
                    </div>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><x-icon name="check" />{{ __('inventory.moves.issue') }}</button>
                    @error('quantity')<span class="field-error sm:col-span-3">{{ $message }}</span>@enderror
                </form>
            @endif
            <button type="button" class="btn btn-ghost btn-sm self-start" wire:click="$set('adding', false)">{{ __('app.cancel') }}</button>
        </div>
    @endif

    <ul>
        @forelse($used as $row)
            <li class="border-b border-line-soft px-4 py-2.5 text-sm last:border-0" wire:key="u-{{ $row['part']->id }}">
                <div class="flex items-center gap-3">
                    <span class="min-w-0 flex-1">
                        @can('inventory.view')<a href="{{ route('inventory.parts.show', $row['part']) }}" class="hover:underline">@endcan
                        <span class="font-mono text-xs text-muted">{{ $row['part']->code }}</span> <span class="font-medium">{{ $row['part']->name }}</span>
                        @can('inventory.view')</a>@endcan
                    </span>
                    <span class="num font-semibold">{{ $q($row['quantity']) }} {{ $row['part']->unit }}</span>
                    @if($canCosts)<span class="num w-20 text-right text-fg-2">€ {{ number_format($row['cost'], 2, ',', '.') }}</span>@endif
                    @if($canMove && $row['quantity'] > 0 && $returning !== $row['part']->id)
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="startReturn({{ $row['part']->id }})">{{ __('inventory.order.return') }}</button>
                    @endif
                </div>
                @if($returning === $row['part']->id)
                    <form wire:submit="giveBack" class="mt-2 flex flex-wrap items-center gap-2">
                        <label for="op-ret" class="text-sm text-fg-2">{{ __('inventory.order.return_qty') }}</label>
                        <input id="op-ret" type="text" inputmode="decimal" wire:model="returnQuantity" class="input num w-24">
                        <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="check" class="!w-4 !h-4" />{{ __('inventory.order.return') }}</button>
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('returning', null)">{{ __('app.cancel') }}</button>
                        @error('returnQuantity')<span class="field-error w-full">{{ $message }}</span>@enderror
                    </form>
                @endif
            </li>
        @empty
            <li class="px-4 py-3 text-sm text-muted">{{ __('inventory.order.empty') }}</li>
        @endforelse
    </ul>
</section>
