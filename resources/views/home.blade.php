@php
    $company = (string) settings('company.name');
    $cron = $status['cron_last_run'];
@endphp
<x-layouts.app :title="__('app.home.title')" :heading="__('app.home.title')">
    <div class="mx-auto flex max-w-6xl flex-col gap-6">
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
