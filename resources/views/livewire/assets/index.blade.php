<div class="mx-auto flex max-w-6xl flex-col gap-5">
    {{-- Azioni --}}
    <div class="flex flex-wrap items-center gap-2">
        @if($canManage)
            <a href="{{ route('assets.create') }}" class="btn btn-primary"><x-icon name="plus" />{{ __('assets.new') }}</a>
            <a href="{{ route('assets.import') }}" class="btn btn-secondary"><x-icon name="upload" />{{ __('assets.import.button') }}</a>
        @endif
        <div class="ml-auto flex flex-wrap gap-2">
            <a href="{{ route('assets.locations') }}" class="btn btn-ghost btn-sm"><x-icon name="map-pin" class="!w-4 !h-4" />{{ __('assets.locations.title') }}</a>
            @if($canManage)
                <a href="{{ route('assets.categories') }}" class="btn btn-ghost btn-sm"><x-icon name="tag" class="!w-4 !h-4" />{{ __('assets.categories.title') }}</a>
            @endif
        </div>
    </div>

    @if($everything === 0)
        {{-- Primo avvio: nessuna macchina --}}
        <section class="card flex flex-col items-center gap-4 px-6 py-12 text-center">
            <span class="grid h-16 w-16 place-items-center rounded-2xl bg-surface-2 text-accent-fg"><x-icon name="factory" class="!w-8 !h-8" /></span>
            <h2 class="text-xl font-bold">{{ __('assets.empty.title') }}</h2>
            <p class="max-w-md text-fg-2">{{ __('assets.empty.text') }}</p>
            @if($canManage)
                <div class="flex flex-wrap justify-center gap-2">
                    <a href="{{ route('assets.locations') }}" class="btn btn-secondary"><x-icon name="map-pin" />{{ __('assets.empty.locations') }}</a>
                    <a href="{{ route('assets.create') }}" class="btn btn-primary"><x-icon name="plus" />{{ __('assets.empty.first') }}</a>
                    <a href="{{ route('assets.import') }}" class="btn btn-secondary"><x-icon name="upload" />{{ __('assets.empty.import') }}</a>
                </div>
            @endif
        </section>
    @else
        {{-- Stato delle macchine: si tocca per filtrare --}}
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="{{ __('assets.filter_status') }}">
            <button type="button" wire:click="$set('status', '')" @class(['status-filter', 'is-active' => $status === '']) aria-pressed="{{ $status === '' ? 'true' : 'false' }}">
                {{ __('assets.all') }} <span class="num font-bold">{{ $total }}</span>
            </button>
            @foreach($statuses as $s)
                <button type="button" wire:click="$set('status', '{{ $s->value }}')" @class(['status-filter', 'is-active' => $status === $s->value]) style="--c: var(--mt-{{ $s->tone() }})" aria-pressed="{{ $status === $s->value ? 'true' : 'false' }}">
                    <x-icon :name="$s->icon()" class="!w-4 !h-4 text-[var(--c)]" />{{ $s->label() }} <span class="num font-bold">{{ $counts[$s->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Filtri: sul telefono la ricerca resta visibile, il resto si apre a richiesta --}}
        @php $activeFilters = collect([$location, $category, $criticality])->filter(fn ($v) => $v !== '')->count() + ($sort !== 'code' ? 1 : 0); @endphp
        <div x-data="{ open: false }" class="flex flex-col gap-3">
            <div class="flex items-end gap-2 lg:hidden">
                <div class="field min-w-0 flex-1">
                    <label for="a-search-m" class="field-label">{{ __('assets.search') }}</label>
                    <input id="a-search-m" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="{{ __('assets.search_placeholder') }}" autocomplete="off">
                </div>
                <button type="button" class="btn btn-secondary" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="a-filters">
                    <x-icon name="filter" class="!w-4 !h-4" />{{ __('assets.filters') }}@if($activeFilters) <span class="num rounded-full bg-[var(--mt-accent)] px-1.5 text-xs text-[var(--mt-on-accent)]">{{ $activeFilters }}</span>@endif
                </button>
            </div>
            <div id="a-filters" class="hidden gap-3 sm:grid-cols-2 lg:grid lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1.5fr)_minmax(0,1.2fr)_minmax(0,0.8fr)_minmax(0,1.1fr)]" :class="open && '!grid'">
                <div class="field hidden lg:flex">
                    <label for="a-search" class="field-label">{{ __('assets.search') }}</label>
                    <input id="a-search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="{{ __('assets.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="field">
                    <label for="a-location" class="field-label">{{ __('assets.field.location_id') }}</label>
                    <select id="a-location" wire:model.live="location" class="input">
                        <option value="">{{ __('assets.all_locations') }}</option>
                        @foreach($locations as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="a-category" class="field-label">{{ __('assets.field.asset_category_id') }}</label>
                    <select id="a-category" wire:model.live="category" class="input">
                        <option value="">{{ __('assets.all_categories') }}</option>
                        @foreach($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        <option value="none">{{ __('assets.no_category') }}</option>
                    </select>
                </div>
                <div class="field">
                    <label for="a-crit" class="field-label">{{ __('assets.field.criticality') }}</label>
                    <select id="a-crit" wire:model.live="criticality" class="input">
                        <option value="">{{ __('assets.all') }}</option>
                        @foreach($criticalities as $c)<option value="{{ $c->value }}">{{ $c->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="a-sort" class="field-label">{{ __('assets.sort.label') }}</label>
                    <select id="a-sort" wire:model.live="sort" class="input">
                        @foreach(['code', 'name', 'status', 'recent'] as $s)<option value="{{ $s }}">{{ __('assets.sort.'.$s) }}</option>@endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Barra della selezione per le etichette --}}
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="text-muted">{{ trans_choice('assets.found', $assets->total(), ['count' => $assets->total()]) }}</span>
            @if($filtered)<button type="button" class="btn btn-ghost btn-sm" wire:click="clearFilters"><x-icon name="x" class="!w-4 !h-4" />{{ __('assets.clear_filters') }}</button>@endif
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <button type="button" class="btn btn-ghost btn-sm" wire:click="selectAll"><x-icon name="check" class="!w-4 !h-4" />{{ __('assets.labels.select_all') }}</button>
                @if($selected !== [])
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('selected', [])">{{ __('assets.labels.clear') }}</button>
                    <a href="{{ route('assets.labels', ['ids' => implode(',', $selected)]) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="printer" class="!w-4 !h-4" />{{ trans_choice('assets.labels.print_selected', count($selected), ['count' => count($selected)]) }}</a>
                @endif
            </div>
        </div>

        <section class="card overflow-hidden" aria-label="{{ __('assets.title') }}">
            {{-- PC: tabella --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="table">
                    <thead><tr>
                        <th class="w-10"><span class="sr-only">{{ __('assets.labels.select') }}</span></th>
                        <th colspan="2">{{ __('assets.field.name') }}</th>
                        <th>{{ __('assets.field.location_id') }}</th>
                        <th>{{ __('assets.field.asset_category_id') }}</th>
                        <th class="text-center">{{ __('assets.field.criticality_short') }}</th>
                        <th>{{ __('assets.field.status') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($assets as $a)
                        <tr wire:key="a-{{ $a->id }}" class="cursor-pointer" onclick="if (! event.target.closest('a,input,label,button')) window.location.assign('{{ route('assets.show', $a) }}')">
                            <td><input type="checkbox" class="h-5 w-5 accent-[var(--mt-accent)]" value="{{ $a->id }}" wire:model.live="selected" aria-label="{{ __('assets.labels.select_one', ['code' => $a->code]) }}"></td>
                            <td class="w-14"><x-asset-thumb :asset="$a" /></td>
                            <td>
                                <a href="{{ route('assets.show', $a) }}" class="font-medium hover:underline">{{ $a->name }}</a>
                                <div class="font-mono text-xs text-muted">{{ $a->code }}@if($a->manufacturer || $a->model) <span class="font-sans">· {{ trim($a->manufacturer.' '.$a->model) }}</span>@endif</div>
                            </td>
                            <td class="max-w-[16rem] truncate text-fg-2" title="{{ $locations[$a->location_id] ?? '' }}">{{ $locations[$a->location_id] ?? '—' }}</td>
                            <td class="text-fg-2">{{ $a->category?->name ?? '—' }}</td>
                            <td class="text-center"><x-criticality :value="$a->criticality" /></td>
                            <td><x-status :status="$a->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-muted">{{ __('assets.none_found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Telefono: schede --}}
            <ul class="md:hidden">
                @forelse($assets as $a)
                    <li class="flex items-center gap-3 border-b border-line-soft px-3 py-2.5 last:border-0" wire:key="am-{{ $a->id }}">
                        <a href="{{ route('assets.show', $a) }}" class="flex min-w-0 flex-1 items-center gap-3">
                            <x-asset-thumb :asset="$a" size="h-12 w-12" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $a->name }}</span>
                                <span class="block truncate text-xs text-muted"><span class="font-mono">{{ $a->code }}</span>@if($a->location_id) · {{ $locations[$a->location_id] ?? '' }}@endif</span>
                                <span class="mt-1 flex items-center gap-2"><x-status :status="$a->status" /><x-criticality :value="$a->criticality" /></span>
                            </span>
                        </a>
                        <input type="checkbox" class="h-6 w-6 shrink-0 accent-[var(--mt-accent)]" value="{{ $a->id }}" wire:model.live="selected" aria-label="{{ __('assets.labels.select_one', ['code' => $a->code]) }}">
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-muted">{{ __('assets.none_found') }}</li>
                @endforelse
            </ul>
        </section>

        {{ $assets->links() }}
    @endif
</div>
