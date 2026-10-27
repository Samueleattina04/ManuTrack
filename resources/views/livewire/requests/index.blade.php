<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-center gap-2">
        @can('requests.create')
            <a href="{{ route('requests.create') }}" class="btn btn-primary"><x-icon name="plus" />{{ __('maintenance.quick.title') }}</a>
        @endcan
        @if($mineOnly)<p class="text-sm text-muted">{{ __('maintenance.request.mine_only') }}</p>@endif
    </div>

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="{{ __('maintenance.filter_status') }}">
        <button type="button" wire:click="$set('status', '')" @class(['status-filter', 'is-active' => $status === '']) aria-pressed="{{ $status === '' ? 'true' : 'false' }}">{{ __('assets.all') }} <span class="num font-bold">{{ $counts->sum() }}</span></button>
        @foreach($statuses as $s)
            <button type="button" wire:click="$set('status', '{{ $s->value }}')" @class(['status-filter', 'is-active' => $status === $s->value]) style="--c: var(--mt-{{ $s->tone() }})" aria-pressed="{{ $status === $s->value ? 'true' : 'false' }}">
                <x-icon :name="$s->icon()" class="!w-4 !h-4 text-[var(--c)]" />{{ $s->label() }} <span class="num font-bold">{{ $counts[$s->value] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="field max-w-md">
        <label for="r-search" class="field-label">{{ __('assets.search') }}</label>
        <input id="r-search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="{{ __('maintenance.request.search_placeholder') }}" data-search>
    </div>

    <section class="card overflow-hidden" aria-label="{{ __('maintenance.request.title') }}">
        <ul>
            @forelse($requests as $r)
                <li class="border-b border-line-soft last:border-0" wire:key="r-{{ $r->id }}">
                    <a href="{{ route('requests.show', $r) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-surface-2">
                        <span @class(['mt-1 h-10 w-1.5 shrink-0 rounded-full', 'bg-[var(--mt-ko)]' => $r->isUrgent(), 'bg-line' => ! $r->isUrgent()]) aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-mono text-xs text-muted">{{ $r->number }}</span>
                                @if($r->isUrgent())<x-status tone="ko" icon="bell">{{ __('maintenance.request.urgent') }}</x-status>@endif
                                @if($r->machine_stopped)<x-status tone="ko" icon="pause-circle">{{ __('maintenance.request.stopped') }}</x-status>@endif
                            </span>
                            <span class="mt-0.5 block font-medium">{{ $r->summary(110) }}</span>
                            <span class="block truncate text-sm text-muted">
                                {{ $r->asset ? $r->asset->code.' · '.$r->asset->name : ($r->location?->fullName() ?? __('maintenance.request.no_asset')) }}
                                · {{ $r->user?->name ?? '—' }} · {{ $r->reported_at?->diffForHumans() }}
                                @if($r->attachments_count) · <x-icon name="camera" class="!inline !w-3.5 !h-3.5" /> {{ $r->attachments_count }}@endif
                            </span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <x-status :status="$r->status" />
                            <x-priority :priority="$r->priority" class="hidden sm:inline-flex" />
                        </span>
                    </a>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-muted">{{ __('maintenance.request.empty') }}</li>
            @endforelse
        </ul>
    </section>

    {{ $requests->links() }}
</div>
