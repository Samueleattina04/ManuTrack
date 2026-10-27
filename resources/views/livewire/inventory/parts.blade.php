@php $q = fn ($v) => \App\Models\Part::formatQty($v); @endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-center gap-2">
        @if($canManage)
            <a href="{{ route('inventory.parts.create') }}" class="btn btn-primary"><x-icon name="plus" />{{ __('inventory.part.new') }}</a>
            <a href="{{ route('inventory.import') }}" class="btn btn-secondary"><x-icon name="upload" />{{ __('assets.import.button') }}</a>
        @endif
        @can('reports.export')
            <x-export-menu route="export.parts" :params="['q' => $search, 'scorta' => $stock, 'magazzino' => $warehouse, 'categoria' => $category, 'disattivati' => $inactive ? '1' : '']" />
        @endcan
        <nav class="ml-auto flex flex-wrap gap-1" aria-label="{{ __('inventory.title') }}">
            <a href="{{ route('inventory.movements') }}" class="btn btn-ghost btn-sm"><x-icon name="history" class="!w-4 !h-4" />{{ __('inventory.movements.title') }}</a>
            <a href="{{ route('inventory.suppliers') }}" class="btn btn-ghost btn-sm"><x-icon name="truck" class="!w-4 !h-4" />{{ __('inventory.suppliers.title') }}</a>
            @if($canManage)
                <a href="{{ route('inventory.reorder') }}" class="btn btn-ghost btn-sm"><x-icon name="clipboard-list" class="!w-4 !h-4" />{{ __('inventory.reorder.title') }}</a>
                <a href="{{ route('inventory.warehouses') }}" class="btn btn-ghost btn-sm"><x-icon name="box" class="!w-4 !h-4" />{{ __('inventory.warehouses.title') }}</a>
            @endif
        </nav>
    </div>

    @if($total === 0)
        <section class="card flex flex-col items-center gap-4 px-6 py-12 text-center">
            <span class="grid h-16 w-16 place-items-center rounded-2xl bg-surface-2 text-accent-fg"><x-icon name="box" class="!w-8 !h-8" /></span>
            <h2 class="text-xl font-bold">{{ __('inventory.empty.title') }}</h2>
            <p class="max-w-md text-fg-2">{{ __('inventory.empty.text') }}</p>
            @if($canManage)
                <div class="flex flex-wrap justify-center gap-2">
                    <a href="{{ route('inventory.warehouses') }}" class="btn btn-secondary"><x-icon name="box" />{{ __('inventory.empty.warehouse') }}</a>
                    <a href="{{ route('inventory.parts.create') }}" class="btn btn-primary"><x-icon name="plus" />{{ __('inventory.part.new') }}</a>
                    <a href="{{ route('inventory.import') }}" class="btn btn-secondary"><x-icon name="upload" />{{ __('assets.empty.import') }}</a>
                </div>
            @endif
        </section>
    @else
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="{{ __('inventory.filter_stock') }}">
            <button type="button" wire:click="$set('stock', '')" @class(['status-filter', 'is-active' => $stock === '']) aria-pressed="{{ $stock === '' ? 'true' : 'false' }}">{{ __('assets.all') }} <span class="num font-bold">{{ $counts['tutti'] }}</span></button>
            <button type="button" wire:click="$set('stock', 'sotto')" @class(['status-filter', 'is-active' => $stock === 'sotto']) style="--c: var(--mt-warn)" aria-pressed="{{ $stock === 'sotto' ? 'true' : 'false' }}"><x-icon name="alert-triangle" class="!w-4 !h-4 text-[var(--c)]" />{{ __('inventory.state.sotto') }} <span class="num font-bold">{{ $counts['sotto'] }}</span></button>
            <button type="button" wire:click="$set('stock', 'esauriti')" @class(['status-filter', 'is-active' => $stock === 'esauriti']) style="--c: var(--mt-ko)" aria-pressed="{{ $stock === 'esauriti' ? 'true' : 'false' }}"><x-icon name="x-circle" class="!w-4 !h-4 text-[var(--c)]" />{{ __('inventory.state.esauriti') }} <span class="num font-bold">{{ $counts['esauriti'] }}</span></button>
        </div>

        {{-- Filtri: sul telefono la ricerca resta visibile, il resto si apre a richiesta --}}
        <div x-data="{ open: false }" class="flex flex-col gap-3">
            <div class="flex items-end gap-2">
                <div class="field min-w-0 flex-1">
                    <label for="p-search" class="field-label">{{ __('assets.search') }}</label>
                    <input id="p-search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="{{ __('inventory.part.search_placeholder') }}" autocomplete="off">
                </div>
                <button type="button" class="btn btn-secondary lg:hidden" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="p-filters">
                    <x-icon name="filter" class="!w-4 !h-4" />{{ __('assets.filters') }}@if($activeFilters) <span class="num rounded-full bg-[var(--mt-accent)] px-1.5 text-xs text-[var(--mt-on-accent)]">{{ $activeFilters }}</span>@endif
                </button>
            </div>
            <div id="p-filters" class="hidden gap-3 sm:grid-cols-3 lg:grid lg:items-end" :class="open && '!grid'">
                <div class="field">
                    <label for="p-wh" class="field-label">{{ __('inventory.field.warehouse') }}</label>
                    <select id="p-wh" wire:model.live="warehouse" class="input"><option value="">{{ __('inventory.all_warehouses') }}</option>@foreach($warehouses as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                </div>
                <div class="field">
                    <label for="p-cat" class="field-label">{{ __('inventory.field.category') }}</label>
                    <select id="p-cat" wire:model.live="category" class="input"><option value="">{{ __('assets.all_categories') }}</option>@foreach($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>
                </div>
                <label class="flex min-h-[44px] items-center gap-2 rounded-[var(--radius-md)] border border-line px-3 text-sm"><input type="checkbox" wire:model.live="inactive" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('inventory.part.show_inactive') }}</label>
            </div>
        </div>

        <section class="card overflow-hidden" aria-label="{{ __('inventory.title') }}">
            <div class="hidden overflow-x-auto md:block">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('inventory.field.name') }}</th>
                        <th>{{ __('inventory.field.category') }}</th>
                        <th class="text-right">{{ __('inventory.field.stock') }}</th>
                        <th class="text-right">{{ __('inventory.field.min_stock') }}</th>
                        @if($canCosts)<th class="text-right">{{ __('inventory.field.unit_cost') }}</th>@endif
                        <th>{{ __('inventory.field.state') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($parts as $p)
                        <tr wire:key="p-{{ $p->id }}" class="cursor-pointer" onclick="if (! event.target.closest('a')) window.location.assign('{{ route('inventory.parts.show', $p) }}')">
                            <td>
                                <a href="{{ route('inventory.parts.show', $p) }}" class="font-medium hover:underline">{{ $p->name }}</a>
                                <div class="font-mono text-xs text-muted">{{ $p->code }}@if($p->manufacturer_code) <span class="font-sans">· {{ $p->manufacturer }} {{ $p->manufacturer_code }}</span>@endif</div>
                            </td>
                            <td class="text-fg-2">{{ $p->category ?? '—' }}</td>
                            <td class="num text-right" title="{{ $p->levels->filter(fn ($l) => (float) $l->quantity != 0)->map(fn ($l) => $l->warehouse->name.': '.$q($l->quantity))->implode(' · ') }}"><span class="font-semibold">{{ $q($p->stock_total) }}</span> <span class="text-muted">{{ $p->unit }}</span></td>
                            <td class="num text-right text-fg-2">{{ (float) $p->min_stock > 0 ? $q($p->min_stock) : '—' }}</td>
                            @if($canCosts)<td class="num text-right text-fg-2">€ {{ number_format((float) $p->unit_cost, 2, ',', '.') }}</td>@endif
                            <td><x-stock-state :part="$p" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-muted">{{ __('inventory.part.none_found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <ul class="md:hidden">
                @forelse($parts as $p)
                    <li class="border-b border-line-soft last:border-0" wire:key="pm-{{ $p->id }}">
                        <a href="{{ route('inventory.parts.show', $p) }}" class="flex items-center gap-3 px-4 py-3">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $p->name }}</span>
                                <span class="block truncate font-mono text-xs text-muted">{{ $p->code }}</span>
                            </span>
                            <span class="flex flex-col items-end gap-1">
                                <span class="num font-semibold">{{ $q($p->stock_total) }} <span class="font-normal text-muted">{{ $p->unit }}</span></span>
                                @if($p->stockState() !== 'ok')<x-stock-state :part="$p" />@endif
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-muted">{{ __('inventory.part.none_found') }}</li>
                @endforelse
            </ul>
        </section>
        {{ $parts->links() }}
    @endif
</div>
