<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-center gap-2">
        @if($canManage)
            <a href="{{ route('preventive.create') }}" class="btn btn-primary"><x-icon name="plus" />{{ __('preventive.plan.new') }}</a>
        @endif
        <a href="{{ route('preventive.calendar') }}" class="btn btn-secondary"><x-icon name="calendar" />{{ __('preventive.calendar.title') }}</a>
    </div>

    @if($total === 0)
        <section class="card flex flex-col items-center gap-4 px-6 py-12 text-center">
            <span class="grid h-16 w-16 place-items-center rounded-2xl bg-surface-2 text-accent-fg"><x-icon name="calendar" class="!w-8 !h-8" /></span>
            <h2 class="text-xl font-bold">{{ __('preventive.plan.empty_title') }}</h2>
            <p class="max-w-md text-fg-2">{{ __('preventive.plan.empty_text') }}</p>
            @if($canManage)<a href="{{ route('preventive.create') }}" class="btn btn-primary"><x-icon name="plus" />{{ __('preventive.plan.new') }}</a>@endif
        </section>
    @else
        {{-- Rispetto dei piani, ultimi 12 mesi --}}
        <section class="card grid gap-4 p-4 sm:grid-cols-[auto_1fr] sm:items-center sm:p-5" aria-labelledby="pv-compliance">
            <div class="flex items-center gap-4">
                <div @class(['grid h-20 w-20 shrink-0 place-items-center rounded-full border-4',
                    'border-[var(--mt-ok)]' => ($compliance['rate'] ?? 0) >= 90,
                    'border-[var(--mt-warn)]' => $compliance['rate'] !== null && $compliance['rate'] < 90 && $compliance['rate'] >= 70,
                    'border-[var(--mt-ko)]' => $compliance['rate'] !== null && $compliance['rate'] < 70,
                    'border-line' => $compliance['rate'] === null])>
                    <span class="num text-xl font-bold">{{ $compliance['rate'] === null ? '—' : number_format($compliance['rate'], 0, ',', '.').'%' }}</span>
                </div>
                <div>
                    <h2 id="pv-compliance" class="text-base font-bold">{{ __('preventive.compliance.title') }}</h2>
                    <p class="text-sm text-muted">{{ __('preventive.compliance.period', ['months' => 12]) }}</p>
                </div>
            </div>
            @if($compliance['total'] === 0)
                <p class="text-sm text-fg-2">{{ __('preventive.compliance.none') }}</p>
            @else
                <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div><dt class="text-muted">{{ __('preventive.compliance.on_time') }}</dt><dd class="num text-lg font-bold text-ok">{{ $compliance['on_time'] }}</dd></div>
                    <div><dt class="text-muted">{{ __('preventive.compliance.late') }}</dt><dd class="num text-lg font-bold text-warn">{{ $compliance['late'] }}</dd></div>
                    <div><dt class="text-muted">{{ __('preventive.compliance.skipped') }}</dt><dd class="num text-lg font-bold">{{ $compliance['skipped'] }}</dd></div>
                    <div><dt class="text-muted">{{ __('preventive.compliance.overdue') }}</dt><dd @class(['num text-lg font-bold', 'text-ko' => $compliance['overdue'] > 0])>{{ $compliance['overdue'] }}</dd></div>
                </dl>
            @endif
        </section>

        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="{{ __('preventive.title') }}">
            <button type="button" wire:click="$set('state', '')" @class(['status-filter', 'is-active' => $state === '']) aria-pressed="{{ $state === '' ? 'true' : 'false' }}">{{ __('preventive.filter.all') }} <span class="num font-bold">{{ $counts['all'] }}</span></button>
            <button type="button" wire:click="$set('state', 'due')" @class(['status-filter', 'is-active' => $state === 'due']) style="--c: var(--mt-warn)" aria-pressed="{{ $state === 'due' ? 'true' : 'false' }}"><x-icon name="clock" class="!w-4 !h-4 text-[var(--c)]" />{{ __('preventive.filter.due') }} <span class="num font-bold">{{ $counts['due'] }}</span></button>
            <button type="button" wire:click="$set('state', 'overdue')" @class(['status-filter', 'is-active' => $state === 'overdue']) style="--c: var(--mt-ko)" aria-pressed="{{ $state === 'overdue' ? 'true' : 'false' }}"><x-icon name="alert-triangle" class="!w-4 !h-4 text-[var(--c)]" />{{ __('preventive.filter.overdue') }} <span class="num font-bold">{{ $counts['overdue'] }}</span></button>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div class="field min-w-[16rem] flex-1">
                <label for="pv-search" class="field-label">{{ __('assets.search') }}</label>
                <input id="pv-search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="{{ __('preventive.plan.search_placeholder') }}" autocomplete="off">
            </div>
            <label class="flex min-h-[44px] items-center gap-2 rounded-[var(--radius-md)] border border-line px-3 text-sm"><input type="checkbox" wire:model.live="inactive" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('preventive.plan.show_inactive') }}</label>
        </div>

        <section class="card overflow-hidden" aria-label="{{ __('preventive.title') }}">
            <ul>
                @forelse($rows as $row)
                    @php $p = $row['plan']; @endphp
                    <li class="border-b border-line-soft last:border-0" wire:key="pl-{{ $p->id }}">
                        <a href="{{ route('preventive.show', $p) }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 hover:bg-surface-2">
                            <x-icon :name="$p->trigger->icon()" class="!w-5 !h-5 shrink-0 text-muted" />
                            <span class="min-w-0 flex-1 basis-56">
                                <span class="block truncate font-semibold">{{ $p->title }}</span>
                                <span class="block truncate text-xs text-muted"><span class="font-mono">{{ $p->asset->code }}</span> · {{ $p->asset->name }} · {{ $p->frequencyLabel() }}@if($p->assignee) · {{ $p->assignee->name }}@endif</span>
                            </span>
                            <span class="text-sm sm:w-44 sm:text-right">
                                @if($p->isTime())
                                    <span class="num font-semibold">{{ $p->next_due_on?->translatedFormat('j M Y') ?? '—' }}</span>
                                @else
                                    <span class="num font-semibold">{{ \App\Models\Part::formatQty($p->next_meter_due) }} {{ $p->meter->unit ?? '' }}</span>
                                    <span class="block text-xs text-muted">{{ __('preventive.hint.meter_current', ['value' => \App\Models\Part::formatQty($p->meter->current_value ?? 0), 'unit' => $p->meter->unit ?? '']) }}</span>
                                @endif
                            </span>
                            <span class="flex items-center gap-2 sm:w-52 sm:justify-end">
                                @if($p->hasOpenOrder())<span class="font-mono text-xs text-accent-fg">{{ $p->currentOrder->number }}</span>@endif
                                <x-plan-state :state="$row['state']" />
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-muted">{{ __('preventive.plan.none_found') }}</li>
                @endforelse
            </ul>
        </section>
    @endif
</div>
