@php
    $company = (string) settings('company.name');
    $cron = $status['cron_last_run'];
@endphp
<x-layouts.app :title="__('app.home.title')" :heading="__('app.home.title')">
    <div class="mx-auto flex max-w-6xl flex-col gap-6">
        {{-- Invito a installare l'app e attivare le notifiche (solo telefono, finché serve) --}}
        <div x-data="appPrompt" x-show="visible" x-cloak class="card flex items-center gap-4 border-accent-fg/40 p-4">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-[14px] bg-surface-2 text-accent-fg"><x-icon name="smartphone" class="!h-6 !w-6" /></span>
            <div class="min-w-0 flex-1">
                <strong class="block" x-text="env.standalone ? @js(__('pwa.prompt.notifications_title')) : @js(__('pwa.prompt.title'))"></strong>
                <span class="text-sm text-fg-2" x-text="env.standalone ? @js(__('pwa.prompt.notifications_text')) : @js(__('pwa.prompt.text'))"></span>
            </div>
            <div class="flex flex-col gap-1 sm:flex-row">
                <a href="{{ route('pwa.install') }}" class="btn btn-primary btn-sm">{{ __('pwa.prompt.action') }}</a>
                <button type="button" class="btn btn-ghost btn-sm" @click="dismiss()">{{ __('pwa.prompt.dismiss') }}</button>
            </div>
        </div>

        <section class="card relative overflow-hidden p-6 lg:p-8">
            <div class="pointer-events-none absolute -top-24 -right-16 h-72 w-72 rounded-full" style="background: radial-gradient(closest-side, var(--mt-glow), transparent)"></div>
            <div class="relative grid items-center gap-6 lg:grid-cols-[1fr_auto]">
                <div class="flex flex-col gap-3">
                    <span class="eyebrow">{{ $company }}</span>
                    <h2 class="text-2xl font-bold lg:text-3xl">{{ __('app.home.welcome_user', ['name' => \Illuminate\Support\Str::before(auth()->user()->name, ' ')]) }}</h2>
                    <p class="max-w-prose text-fg-2">{{ __('app.home.intro', ['company' => $company]) }}</p>
                    @can('users.manage')
                        <div class="mt-2 flex flex-wrap gap-2">
                            <a href="{{ route('users.index') }}" class="btn btn-primary"><x-icon name="users" />{{ __('app.home.open_users') }}</a>
                            <a href="{{ route('settings.company') }}" class="btn btn-secondary"><x-icon name="settings" />{{ __('app.home.open_settings') }}</a>
                        </div>
                    @endcan
                </div>
                <x-logo variant="mark" class="hidden w-40 lg:block" :label="null" />
            </div>
        </section>

        @if($down !== null)
            <section class="card overflow-hidden" aria-labelledby="home-down">
                <div class="flex items-center gap-2 border-b border-line-soft px-4 py-3">
                    <h2 id="home-down" class="flex-1 text-base font-bold">{{ __('app.home.down_title') }}</h2>
                    <a href="{{ route('assets.index') }}" class="btn btn-ghost btn-sm shrink-0">{{ __('app.home.all_assets') }}<x-icon name="chevron-right" class="!w-4 !h-4" /></a>
                </div>
                @if($down->isEmpty())
                    <p class="flex items-center gap-2 px-4 py-4 text-sm text-fg-2"><x-icon name="check-circle" class="text-ok" />{{ $assetCount ? __('app.home.down_none') : __('app.home.no_assets') }}</p>
                @else
                    <ul>
                        @foreach($down as $a)
                            <li class="border-b border-line-soft last:border-0">
                                <a href="{{ route('assets.show', $a) }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface-2">
                                    <x-asset-thumb :asset="$a" size="h-10 w-10" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium">{{ $a->name }}</span>
                                        <span class="block truncate text-xs text-muted"><span class="font-mono">{{ $a->code }}</span>@if($a->location) · {{ $a->location->fullName() }}@endif · {{ $a->status_changed_at?->diffForHumans() }}</span>
                                    </span>
                                    <span class="hidden sm:block"><x-criticality :value="$a->criticality" /></span>
                                    <x-status :status="$a->status" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @can('system.view')
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('system.title') }}">
            <div class="card tilt p-4" x-tilt>
                <div class="flex items-center justify-between text-xs text-muted"><span>{{ __('app.home.version') }}</span><x-icon name="sparkles" class="!w-4 !h-4" /></div>
                <div class="mt-1 font-display text-2xl font-bold num">{{ $status['code_version'] }}</div>
                <div class="mt-2"><x-status tone="ok" icon="check-circle">{{ __('system.version.installed') }} {{ $status['installed_version'] ?? __('system.version.none') }}</x-status></div>
            </div>
            <div class="card tilt p-4" x-tilt>
                <div class="flex items-center justify-between text-xs text-muted"><span>{{ __('app.home.cron') }}</span><x-icon name="clock" class="!w-4 !h-4" /></div>
                <div class="mt-1 font-display text-2xl font-bold">{{ $status['cron_healthy'] ? 'OK' : '—' }}</div>
                <div class="mt-2">
                    @if($status['cron_healthy'])
                        <x-status tone="ok" icon="check-circle">{{ __('app.home.cron_ok', ['time' => $cron->diffForHumans()]) }}</x-status>
                    @elseif($cron)
                        <x-status tone="warn" icon="alert-triangle">{{ __('app.home.cron_late', ['time' => $cron->diffForHumans(null, true)]) }}</x-status>
                    @else
                        <x-status tone="warn" icon="alert-triangle">{{ __('app.home.cron_missing') }}</x-status>
                    @endif
                </div>
            </div>
            <div class="card tilt p-4" x-tilt>
                <div class="flex items-center justify-between text-xs text-muted"><span>{{ __('app.home.queue') }}</span><x-icon name="database" class="!w-4 !h-4" /></div>
                <div class="mt-1 font-display text-2xl font-bold num"><span x-count-up>{{ $status['pending_jobs'] ?? 0 }}</span></div>
                <div class="mt-2 text-sm text-fg-2">{{ __('app.home.queue_detail', ['pending' => $status['pending_jobs'] ?? 0, 'failed' => $status['failed_jobs'] ?? 0]) }} · <span class="font-mono">{{ $status['queue_driver'] }}</span></div>
            </div>
            <div class="card tilt p-4" x-tilt>
                <div class="flex items-center justify-between text-xs text-muted"><span>{{ __('app.home.push') }}</span><x-icon name="bell" class="!w-4 !h-4" /></div>
                <div class="mt-1 font-display text-2xl font-bold">{{ $status['push_configured'] ? 'VAPID' : '—' }}</div>
                <div class="mt-2">
                    @if($status['push_configured'])
                        <x-status tone="ok" icon="check-circle">{{ __('app.home.push_ok') }}</x-status>
                    @else
                        <x-status tone="warn" icon="key">{{ __('app.home.push_missing') }}</x-status>
                    @endif
                </div>
            </div>
        </section>

        @if($status['image_driver'] === null)
            <div class="alert alert-warn"><x-icon name="info" /><span>{{ __('app.home.images_none') }}</span></div>
        @endif
        @endcan
    </div>
</x-layouts.app>
