<div class="mx-auto flex max-w-3xl flex-col gap-5">
    <div class="flex flex-wrap items-center gap-2">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="unreadOnly" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('notifications.unread_only') }}</label>
        <div class="ml-auto flex flex-wrap gap-2">
            @if($unread > 0)<button type="button" class="btn btn-secondary btn-sm" wire:click="markAllRead"><x-icon name="check" class="!w-4 !h-4" />{{ __('notifications.all_read') }}</button>@endif
            <a href="{{ route('notifications.preferences') }}" class="btn btn-ghost btn-sm"><x-icon name="settings" class="!w-4 !h-4" />{{ __('notifications.preferences.title') }}</a>
        </div>
    </div>

    @forelse($days as $day => $rows)
        <section class="card overflow-hidden" aria-labelledby="day-{{ $day }}">
            <h2 id="day-{{ $day }}" class="border-b border-line-soft px-4 py-2 text-sm font-semibold text-fg-2 first-letter:uppercase">
                {{ \Carbon\Carbon::parse($day)->isToday() ? __('notifications.today') : (\Carbon\Carbon::parse($day)->isYesterday() ? __('notifications.yesterday') : \Carbon\Carbon::parse($day)->translatedFormat('l j F')) }}
            </h2>
            <ul>
                @foreach($rows as $r)
                    @php $n = $r['item']; @endphp
                    <li class="border-b border-line-soft last:border-0" wire:key="n-{{ $n->id }}">
                        <button type="button" wire:click="open({{ $n->id }})" @class(['flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-surface-2', 'bg-[color-mix(in_oklab,var(--mt-accent)_7%,transparent)]' => $r['unread']])>
                            <span @class(['mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-full', 'bg-[color-mix(in_oklab,var(--mt-ko)_16%,transparent)] text-ko' => $n->urgent, 'bg-surface-2 text-fg-2' => ! $n->urgent])><x-icon :name="$n->event->icon()" class="!w-5 !h-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span @class(['block', 'font-semibold' => $r['unread']])>{{ $n->title }}@if($r['count'] > 1) <span class="num text-sm font-normal text-muted">× {{ $r['count'] }}</span>@endif</span>
                                @if($n->body)<span class="block truncate text-sm text-fg-2">{{ $n->body }}</span>@endif
                                <span class="block text-xs text-muted">{{ $n->created_at->format('H:i') }} · {{ $n->event->label() }}</span>
                            </span>
                            @if($r['unread'])<span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-[var(--mt-accent)]" aria-label="{{ __('notifications.new') }}"></span>@endif
                        </button>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <section class="card flex flex-col items-center gap-3 px-6 py-12 text-center">
            <x-icon name="bell" class="!w-10 !h-10 text-muted" />
            <p class="text-fg-2">{{ $unreadOnly ? __('notifications.none_unread') : __('notifications.none') }}</p>
        </section>
    @endforelse
    {{ $page->links() }}
</div>
