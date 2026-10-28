@php $q = fn ($v) => \App\Models\Part::formatQty($v); @endphp
<div class="mx-auto flex max-w-5xl flex-col gap-5">
    <div class="flex flex-wrap items-center gap-2 print:hidden">
        <a href="{{ route('inventory.parts.index') }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('inventory.title') }}</a>
        @if($count)<button type="button" class="btn btn-secondary btn-sm ml-auto" x-data x-on:click="window.print()"><x-icon name="printer" class="!w-4 !h-4" />{{ __('assets.labels.print') }}</button>@endif
    </div>
    <p class="text-sm text-fg-2">{{ __('inventory.reorder.intro') }}</p>

    @forelse($groups as $g)
        <section class="card overflow-hidden" x-data="{ copied: false }">
            <div class="flex flex-wrap items-center gap-2 border-b border-line-soft px-4 py-3">
                <h2 class="flex-1 text-base font-bold">{{ $g['supplier']?->name ?? __('inventory.reorder.no_supplier') }}</h2>
                @if($g['supplier']?->email)<a href="mailto:{{ $g['supplier']->email }}" class="text-sm text-accent-fg underline">{{ $g['supplier']->email }}</a>@endif
                @if($g['supplier']?->phone)<span class="text-sm text-muted">{{ $g['supplier']->phone }}</span>@endif
                <button type="button" class="btn btn-ghost btn-sm print:hidden" x-ref="btn"
                        @click="navigator.clipboard.writeText($refs.list.innerText).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                    <x-icon name="clipboard-list" class="!w-4 !h-4" /><span x-text="copied ? @js(__('inventory.reorder.copied')) : @js(__('inventory.reorder.copy'))"></span>
                </button>
            </div>
            <table class="table">
                <thead><tr><th>{{ __('inventory.field.name') }}</th><th>{{ __('inventory.field.supplier_code') }}</th><th class="text-right">{{ __('inventory.field.stock') }}</th><th class="text-right">{{ __('inventory.field.min_stock') }}</th><th class="text-right">{{ __('inventory.reorder.order') }}</th></tr></thead>
                <tbody>
                    @foreach($g['parts'] as $p)
                        <tr>
                            <td><a href="{{ route('inventory.parts.show', $p) }}" class="hover:underline"><span class="font-mono text-xs">{{ $p->code }}</span> {{ $p->name }}</a></td>
                            <td class="font-mono text-xs">{{ $p->preferredSupplier()?->pivot->supplier_code ?? $p->manufacturer_code ?? '—' }}</td>
                            <td @class(['num text-right', 'text-ko font-semibold' => (float) $p->stock_total <= 0])>{{ $q($p->stock_total) }}</td>
                            <td class="num text-right text-fg-2">{{ $q($p->min_stock) }}</td>
                            <td class="num text-right font-bold">{{ $q($p->suggestedOrder()) }} {{ $p->unit }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{-- Testo per l'ordine, pronto da incollare in una mail --}}
            <pre x-ref="list" class="sr-only">@foreach($g['parts'] as $p){{ ($p->preferredSupplier()?->pivot->supplier_code ?? $p->manufacturer_code ?? $p->code) }} — {{ $p->name }} — {{ $q($p->suggestedOrder()) }} {{ $p->unit }}
@endforeach</pre>
        </section>
    @empty
        <section class="card flex flex-col items-center gap-3 px-6 py-10 text-center">
            <x-icon name="check-circle" class="!w-10 !h-10 text-ok" />
            <p class="text-fg-2">{{ __('inventory.reorder.empty') }}</p>
        </section>
    @endforelse
</div>
