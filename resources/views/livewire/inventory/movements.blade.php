@php $q = fn ($v) => \App\Models\Part::formatQty($v); @endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <a href="{{ route('inventory.parts.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('inventory.title') }}</a>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="field">
            <label for="m-type" class="field-label">{{ __('inventory.movements.type') }}</label>
            <select id="m-type" wire:model.live="type" class="input"><option value="">{{ __('assets.all') }}</option>@foreach($types as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select>
        </div>
        <div class="field">
            <label for="m-wh" class="field-label">{{ __('inventory.field.warehouse') }}</label>
            <select id="m-wh" wire:model.live="warehouse" class="input"><option value="">{{ __('inventory.all_warehouses') }}</option>@foreach($warehouses as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
        </div>
        <x-form.input name="from" type="date" :label="__('inventory.movements.from')" wire:model.live="from" />
        <x-form.input name="to" type="date" :label="__('inventory.movements.to')" wire:model.live="to" />
    </div>
    @if($partFilter)
        <div class="flex items-center gap-2 text-sm"><x-icon name="box" class="!w-4 !h-4 text-muted" />{{ $partFilter->code }} · {{ $partFilter->name }}
            <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('part', '')"><x-icon name="x" class="!w-4 !h-4" />{{ __('assets.clear_filters') }}</button></div>
    @endif

    <section class="card overflow-hidden" aria-label="{{ __('inventory.movements.title') }}">
        <div class="overflow-x-auto">
            <table class="table min-w-[760px]">
                <thead><tr>
                    <th>{{ __('inventory.movements.when') }}</th><th>{{ __('inventory.movements.type') }}</th><th>{{ __('inventory.field.name') }}</th><th>{{ __('inventory.field.warehouse') }}</th>
                    <th class="text-right">{{ __('inventory.field.quantity') }}</th><th class="text-right">{{ __('inventory.movements.balance_short') }}</th>@if($canCosts)<th class="text-right">{{ __('inventory.movements.value') }}</th>@endif<th>{{ __('inventory.movements.details') }}</th>
                </tr></thead>
                <tbody>
                @forelse($movements as $m)
                    @php $in = (float) $m->quantity > 0; @endphp
                    <tr wire:key="m-{{ $m->id }}">
                        <td class="num whitespace-nowrap text-fg-2">{{ $m->created_at->translatedFormat('j M Y H:i') }}</td>
                        <td class="whitespace-nowrap"><x-icon :name="$m->type->icon()" @class(['!w-4 !h-4', 'text-ok' => $in, 'text-warn' => ! $in]) /> {{ $m->type->label() }}</td>
                        <td><a href="{{ route('inventory.parts.show', $m->part) }}" class="hover:underline"><span class="font-mono text-xs">{{ $m->part->code }}</span> {{ $m->part->name }}</a></td>
                        <td class="text-fg-2">{{ $m->warehouse->name }}</td>
                        <td @class(['num text-right font-semibold', 'text-ok' => $in])>{{ $in ? '+' : '−' }}{{ $q(abs((float) $m->quantity)) }} <span class="font-normal text-muted">{{ $m->part->unit }}</span></td>
                        <td class="num text-right text-fg-2">{{ $q($m->balance_after) }}</td>
                        @if($canCosts)<td class="num whitespace-nowrap text-right text-fg-2">€ {{ number_format($m->value(), 2, ',', '.') }}</td>@endif
                        <td class="text-fg-2">
                            {{ $m->user?->name ?? '—' }}
                            @if($m->workOrder) · <a href="{{ route('work-orders.show', $m->workOrder) }}" class="underline">{{ $m->workOrder->number }}</a>@endif
                            @if($m->supplier) · {{ $m->supplier->name }}@endif
                            @if($m->reference) · {{ $m->reference }}@endif
                            @if($m->note) · <span class="text-muted">{{ $m->note }}</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-muted">{{ __('inventory.movements.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    {{ $movements->links() }}
</div>
