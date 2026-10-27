<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('preventive.index') }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('preventive.title') }}</a>
        <div class="ml-auto flex items-center gap-1">
            <button type="button" class="btn btn-ghost btn-icon" wire:click="move(-1)" aria-label="{{ __('preventive.calendar.prev') }}"><x-icon name="arrow-left" /></button>
            <h2 class="min-w-[10rem] text-center text-lg font-bold first-letter:uppercase" aria-live="polite">{{ $start->translatedFormat('F Y') }}</h2>
            <button type="button" class="btn btn-ghost btn-icon" wire:click="move(1)" aria-label="{{ __('preventive.calendar.next') }}"><x-icon name="arrow-right" /></button>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="today">{{ __('preventive.calendar.today') }}</button>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-fg-2">
        <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="mine" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('maintenance.order.mine') }}</label>
        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-[var(--mt-accent)]"></span>{{ __('preventive.calendar.legend_order') }}</span>
        <span class="flex items-center gap-1.5"><span class="h-3 w-4 rounded-sm border border-dashed border-[var(--mt-accent)]"></span>{{ __('preventive.calendar.legend_planned') }}</span>
        <span class="flex items-center gap-1.5"><x-icon name="gauge" class="!w-3.5 !h-3.5" />{{ __('preventive.calendar.legend_estimated') }}</span>
    </div>

    {{-- PC: griglia del mese --}}
    <section class="card hidden overflow-hidden md:block" aria-label="{{ __('preventive.calendar.title') }}">
        <div class="grid grid-cols-7 border-b border-line-soft text-center text-xs font-semibold uppercase tracking-wide text-muted">
            @foreach($weekdays as $wd)<div class="py-2">{{ $wd }}</div>@endforeach
        </div>
        @foreach($weeks as $week)
            <div class="grid grid-cols-7 border-b border-line-soft last:border-0">
                @foreach($week as $d)
                    @php $key = $d->toDateString(); $items = $days[$key] ?? []; $inMonth = $d->month === $start->month; @endphp
                    <div @class(['min-h-[7.5rem] border-r border-line-soft p-1.5 last:border-r-0', 'bg-surface-2/50' => ! $inMonth])>
                        <div @class(['mb-1 flex h-6 w-6 items-center justify-center rounded-full text-xs num', 'bg-[var(--mt-accent)] font-bold text-[var(--mt-on-accent)]' => $key === $today, 'text-muted' => ! $inMonth])>{{ $d->day }}</div>
                        <ul class="flex flex-col gap-1">
                            @foreach(array_slice($items, 0, 4) as $it)
                                <li>
                                    <a href="{{ $it['url'] }}" title="{{ $it['asset'] }} · {{ $it['title'] }}" @class([
                                        'block truncate rounded px-1.5 py-0.5 text-xs',
                                        'bg-surface-2 text-muted line-through decoration-1' => $it['kind'] === 'order' && ($it['done'] ?? false),
                                        'bg-[color-mix(in_oklab,var(--mt-accent)_18%,transparent)] font-medium' => $it['kind'] === 'order' && ! $it['overdue'] && ! ($it['done'] ?? false),
                                        'bg-[color-mix(in_oklab,var(--mt-ko)_18%,transparent)] font-medium' => $it['kind'] === 'order' && $it['overdue'],
                                        'border border-dashed border-[var(--mt-accent)] text-fg-2' => $it['kind'] === 'planned' && ! $it['overdue'],
                                        'border border-dashed border-[var(--mt-ko)] text-fg-2' => $it['kind'] === 'planned' && $it['overdue'],
                                    ])>@if(($it['estimated'] ?? false))<x-icon name="gauge" class="!w-3 !h-3" /> @endif<span class="font-mono">{{ $it['asset'] }}</span> {{ $it['title'] }}</a>
                                </li>
                            @endforeach
                            @if(count($items) > 4)<li class="px-1.5 text-xs text-muted">{{ __('preventive.calendar.more', ['count' => count($items) - 4]) }}</li>@endif
                        </ul>
                    </div>
                @endforeach
            </div>
        @endforeach
    </section>

    {{-- Telefono: elenco dei giorni con qualcosa in programma --}}
    <section class="card overflow-hidden md:hidden" aria-labelledby="cal-list">
        <h2 id="cal-list" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('preventive.calendar.list_title') }}</h2>
        @php $monthDays = array_filter($days, fn ($k) => str_starts_with($k, $start->format('Y-m')), ARRAY_FILTER_USE_KEY); @endphp
        @forelse($monthDays as $key => $items)
            <div class="border-b border-line-soft px-4 py-3 last:border-0">
                <h3 @class(['mb-2 text-sm font-bold first-letter:uppercase', 'text-accent-fg' => $key === $today])>{{ \Carbon\CarbonImmutable::parse($key)->translatedFormat('l j F') }}</h3>
                <ul class="flex flex-col gap-2">
                    @foreach($items as $it)
                        <li>
                            <a href="{{ $it['url'] }}" @class(['flex items-center gap-3 rounded-[var(--radius-md)] px-3 py-2.5', 'bg-surface-2' => $it['kind'] === 'order', 'border border-dashed border-line' => $it['kind'] === 'planned'])>
                                <x-icon :name="$it['kind'] === 'order' ? 'clipboard-list' : (($it['estimated'] ?? false) ? 'gauge' : 'calendar')" @class(['!w-5 !h-5', 'text-ko' => $it['overdue'], 'text-muted' => ! $it['overdue']]) />
                                <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $it['title'] }}</span><span class="block text-xs text-muted"><span class="font-mono">{{ $it['asset'] }}</span>@if($it['kind'] === 'order') · {{ $it['number'] }}@elseif($it['estimated'] ?? false) · {{ __('preventive.plan.estimated') }}@endif</span></span>
                                @if($it['kind'] === 'order')<x-status :status="$it['status']" />@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="px-4 py-6 text-center text-sm text-muted">{{ __('preventive.calendar.empty_day') }}</p>
        @endforelse
    </section>
</div>
