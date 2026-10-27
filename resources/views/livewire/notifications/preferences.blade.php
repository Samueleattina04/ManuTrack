<form wire:submit="save" class="mx-auto flex max-w-3xl flex-col gap-5">
    <a href="{{ route('notifications.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('notifications.title') }}</a>
    <p class="text-sm text-fg-2">{{ __('notifications.preferences.intro') }}</p>
    @unless($hasDevice)
        <div class="alert alert-info" role="note"><x-icon name="smartphone" /><span>{!! __('notifications.preferences.no_device', ['url' => e(route('pwa.install'))]) !!}</span></div>
    @endunless

    <section class="card overflow-hidden" aria-label="{{ __('notifications.preferences.title') }}">
        <ul>
            @foreach($events as $e)
                <li class="flex flex-col gap-3 border-b border-line-soft px-4 py-4 last:border-0 sm:flex-row sm:items-center" wire:key="pref-{{ $e->value }}">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <x-icon :name="$e->icon()" class="mt-0.5 !w-5 !h-5 text-muted" />
                        <div class="min-w-0"><p class="font-semibold">{{ $e->label() }}</p><p class="text-sm text-fg-2">{{ $e->description() }}</p></div>
                    </div>
                    <div class="flex items-center gap-3 pl-8 sm:pl-0">
                        <label class="flex min-h-[44px] items-center gap-2 text-sm"><input type="checkbox" wire:model="prefs.{{ $e->value }}.push" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('notifications.channels.push') }}</label>
                        <label class="sr-only" for="em-{{ $e->value }}">{{ __('notifications.channels.email') }}</label>
                        <select id="em-{{ $e->value }}" wire:model="prefs.{{ $e->value }}.email" class="input w-auto">
                            @foreach($modes as $m)<option value="{{ $m }}">{{ __('notifications.email_modes.'.$m) }}</option>@endforeach
                        </select>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
    <p class="text-xs text-muted">{{ __('notifications.preferences.in_app') }}</p>
    <button type="submit" class="btn btn-primary btn-xl self-start"><x-icon name="check" />{{ __('app.save') }}</button>
</form>
