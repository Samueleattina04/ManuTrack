{{-- Scorciatoie della pagina: N nuovo ordine, F cerca, M solo i miei --}}
<div class="mx-auto flex max-w-6xl flex-col gap-5"
     x-data="{ typing(e) { const t = e.target; return t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName) } }"
     @keydown.window="if (typing($event) || $event.metaKey || $event.ctrlKey || $event.altKey) return;
        if ($event.key === 'n' && $refs.create) { $event.preventDefault(); $refs.create.click() }
        else if ($event.key === 'f') { $event.preventDefault(); $refs.search.focus() }
        else if ($event.key === 'm') { $event.preventDefault(); $wire.set('mine', ! $wire.mine) }">
    <div class="flex flex-wrap items-center gap-2">
        @can('work_orders.create')
            <a href="{{ route('work-orders.create') }}" x-ref="create" class="btn btn-primary"><x-icon name="plus" />{{ __('maintenance.order.new') }} <span class="kbd hidden lg:inline-flex">N</span></a>
        @endcan
        @can('reports.export')
            <x-export-menu class="ml-auto" route="export.work-orders" :params="['vista' => $tab, 'miei' => $mine ? '1' : '0', 'q' => $search, 'priorita' => $priority, 'tipo' => $type, 'macchina' => $asset]" />
        @endcan
        @can('work_orders.settings')
            <a href="{{ route('work-orders.settings') }}" @class(['btn btn-ghost btn-sm', 'ml-auto' => ! auth()->user()->can('reports.export')])><x-icon name="settings" class="!w-4 !h-4" />{{ __('maintenance.settings.title') }}</a>
        @endcan
    </div>

    @if($overdue > 0)
        <div class="alert alert-warn" role="status"><x-icon name="clock" /><span>{{ trans_choice('maintenance.order.overdue_count', $overdue, ['count' => $overdue]) }}</span></div>
    @endif

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="tablist" aria-label="{{ __('maintenance.filter_status') }}">
        @foreach(array_keys(\App\Livewire\WorkOrders\Index::TABS) as $t)
            <button type="button" role="tab" aria-selected="{{ $tab === $t ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $t }}')" @class(['status-filter', 'is-active' => $tab === $t])>
                {{ __('maintenance.order.tabs.'.$t) }} <span class="num font-bold">{{ $tabCounts[$t] }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div class="field min-w-[14rem] flex-1">
            <label for="o-search" class="field-label">{{ __('assets.search') }} <span class="kbd ml-1 hidden lg:inline-flex">F</span></label>
            <input id="o-search" x-ref="search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="{{ __('maintenance.order.search_placeholder') }}">
        </div>
        <div class="field">
            <label for="o-prio" class="field-label">{{ __('maintenance.field.priority') }}</label>
            <select id="o-prio" wire:model.live="priority" class="input"><option value="">{{ __('assets.all') }}</option>@foreach($priorities as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select>
        </div>
        <div class="field">
            <label for="o-type" class="field-label">{{ __('maintenance.field.type') }}</label>
            <select id="o-type" wire:model.live="type" class="input"><option value="">{{ __('assets.all') }}</option>@foreach($types as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select>
        </div>
        <label class="flex min-h-[44px] items-center gap-2 rounded-[var(--radius-md)] border border-line px-3">
            <input type="checkbox" wire:model.live="mine" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('maintenance.order.mine') }} <span class="kbd hidden lg:inline-flex">M</span>
        </label>
    </div>

    @if($assetFilter)
        <div class="flex items-center gap-2 text-sm"><x-icon name="factory" class="!w-4 !h-4 text-muted" />{{ __('maintenance.order.for_asset', ['asset' => $assetFilter->code.' · '.$assetFilter->name]) }}
            <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('asset', '')"><x-icon name="x" class="!w-4 !h-4" />{{ __('assets.clear_filters') }}</button></div>
    @endif

    <section class="card overflow-hidden" aria-label="{{ __('maintenance.order.title') }}">
        <ul>
            @forelse($orders as $o)
                <li class="border-b border-line-soft last:border-0" wire:key="o-{{ $o->id }}">
                    <a href="{{ route('work-orders.show', $o) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-surface-2">
                        <span class="mt-1 shrink-0"><x-priority :priority="$o->priority" class="[&>span:last-child]:sr-only" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                                <span class="font-mono">{{ $o->number }}</span>
                                <span class="inline-flex items-center gap-1"><x-icon :name="$o->type->icon()" class="!w-3.5 !h-3.5" />{{ $o->type->label() }}</span>
                                @if($o->due_at)<span @class(['font-semibold text-ko' => $o->isOverdue()])>· {{ __('maintenance.order.due', ['date' => $o->due_at->translatedFormat('j M')]) }}</span>@endif
                            </span>
                            <span class="mt-0.5 block font-medium">{{ $o->title }}</span>
                            <span class="block truncate text-sm text-muted">
                                @if($o->asset){{ $o->asset->code }} · {{ $o->asset->name }}@endif
                                @if($o->assignees->isNotEmpty()) · {{ $o->assignees->pluck('name')->implode(', ') }}@else · <span class="text-warn">{{ __('maintenance.order.unassigned') }}</span>@endif
                            </span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <x-status :status="$o->status" />
                            @if($o->waiting_reason)<span class="text-xs text-muted">{{ $o->waiting_reason->label() }}</span>@endif
                        </span>
                    </a>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-muted">{{ $mine ? __('maintenance.order.empty_mine') : __('maintenance.order.empty') }}</li>
            @endforelse
        </ul>
    </section>

    {{ $orders->links() }}
</div>
