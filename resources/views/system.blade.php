@php
    $error = $status['update_error'];
    $statusTone = ['success' => 'ok', 'failed' => 'ko', 'running' => 'mnt'];
    $statusIcon = ['success' => 'check-circle', 'failed' => 'alert-triangle', 'running' => 'refresh'];
    $checkTone = ['ok' => 'ok', 'warning' => 'warn', 'error' => 'ko'];
    $checkIcon = ['ok' => 'check-circle', 'warning' => 'alert-triangle', 'error' => 'x-circle'];
@endphp
<x-layouts.app :title="__('system.title')" :heading="__('system.title')">
    <div class="mx-auto flex max-w-6xl flex-col gap-6">
        <p class="max-w-prose text-fg-2">{{ __('system.intro') }}</p>

        @if($error)
            <div class="alert alert-ko" role="alert">
                <x-icon name="alert-triangle" />
                <div class="min-w-0 flex-1">
                    <strong>{{ __('system.banner.failed_title', ['version' => $error['version'] ?? '']) }}</strong>
                    <p class="mt-1 font-mono text-[12.5px] break-words text-fg-2">{{ $error['message'] ?? '' }}</p>
                    @if(!empty($error['backup']))
                        <p class="mt-1 text-fg-2">{{ __('system.update.restore_hint', ['file' => $error['backup']]) }}</p>
                    @endif
                    @can('system.update')
                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('system.update') }}" x-data="{ busy: false }" @submit="busy = true">@csrf<button class="btn btn-primary btn-sm" :disabled="busy"><x-icon name="refresh" />{{ __('system.actions.apply_now') }}</button></form>
                            <form method="POST" action="{{ route('system.retry') }}">@csrf<button class="btn btn-secondary btn-sm">{{ __('system.actions.retry_cron') }}</button></form>
                        </div>
                    @endcan
                </div>
            </div>
        @elseif($status['update_pending'])
            <div class="alert alert-warn" role="status">
                <x-icon name="refresh" />
                <div class="min-w-0 flex-1"><strong>{{ __('system.banner.pending_title', ['version' => $status['code_version']]) }}</strong>
                    @if($status['auto_blocker'])<p class="text-fg-2">{{ $status['auto_blocker'] }}</p>@endif</div>
                @can('system.update')
                    <form method="POST" action="{{ route('system.update') }}" x-data="{ busy: false }" @submit="busy = true">@csrf<button class="btn btn-primary btn-sm" :disabled="busy"><x-icon name="refresh" />{{ __('system.actions.apply_now') }}</button></form>
                @endcan
            </div>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="card p-4">
                <div class="text-xs text-muted">{{ __('system.version.code') }}</div>
                <div class="mt-1 font-display text-3xl font-bold num">{{ $status['code_version'] }}</div>
                <div class="mt-1 text-sm text-fg-2">{{ __('system.version.installed') }}: <span class="font-mono">{{ $status['installed_version'] ?? __('system.version.none') }}</span></div>
            </div>
            <div class="card p-4">
                <div class="text-xs text-muted">{{ __('system.cards.cron') }}</div>
                <div class="mt-2">
                    @if($status['cron_healthy'])
                        <x-status tone="ok" icon="check-circle">{{ __('app.home.cron_ok', ['time' => $status['cron_last_run']->diffForHumans()]) }}</x-status>
                    @else
                        <x-status tone="warn" icon="alert-triangle">{{ $status['cron_last_run'] ? __('app.home.cron_late', ['time' => $status['cron_last_run']->diffForHumans(null, true)]) : __('app.home.cron_missing') }}</x-status>
                    @endif
                </div>
                <div class="mt-2 text-sm text-fg-2">{{ __('system.cards.queue') }}: {{ __('app.home.queue_detail', ['pending' => $status['pending_jobs'] ?? 0, 'failed' => $status['failed_jobs'] ?? 0]) }}</div>
            </div>
            <div class="card p-4">
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                    <dt class="text-muted">{{ __('system.cards.php') }}</dt><dd class="font-mono">{{ $status['php'] }}</dd>
                    <dt class="text-muted">{{ __('system.cards.database') }}</dt><dd class="font-mono truncate">{{ $status['database'] }}</dd>
                    <dt class="text-muted">{{ __('system.cards.images') }}</dt><dd>{{ $status['image_driver'] ? strtoupper($status['image_driver']) : __('install.req.missing') }}</dd>
                    <dt class="text-muted">{{ __('system.cards.push') }}</dt><dd>{{ $status['push_configured'] ? __('app.home.push_ok') : __('app.home.push_missing') }}</dd>
                    <dt class="text-muted">{{ __('system.cards.push_urgent') }}</dt><dd>{{ __('system.push_urgent.'.$status['push_early_response']) }}</dd>
                </dl>
            </div>
        </section>

        <section class="card overflow-hidden">
            <h2 class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('system.history.title') }}</h2>
            @if($history->isEmpty())
                <p class="p-4 text-muted">{{ __('system.history.empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table min-w-[640px]">
                        <thead><tr>
                            <th>{{ __('system.history.version') }}</th><th>{{ __('system.history.from') }}</th><th>{{ __('system.history.status') }}</th>
                            <th>{{ __('system.history.trigger') }}</th><th>{{ __('system.history.date') }}</th><th>{{ __('system.history.duration') }}</th><th>{{ __('system.history.backup') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($history as $row)
                            <tr>
                                <td class="code">{{ $row->version }}</td>
                                <td class="code">{{ $row->previous_version ?? '—' }}</td>
                                <td><x-status :tone="$statusTone[$row->status] ?? 'stop'" :icon="$statusIcon[$row->status] ?? 'info'">{{ __('system.history.statuses.'.$row->status) }}</x-status></td>
                                <td>{{ __('system.history.triggers.'.$row->trigger) }}</td>
                                <td class="num">{{ $row->started_at?->format('d/m/Y H:i') }}</td>
                                <td class="num">{{ $row->durationSeconds() !== null ? $row->durationSeconds().' s' : '—' }}</td>
                                <td class="code max-w-[16rem] truncate" title="{{ $row->backup_file }}">{{ $row->backup_file ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card overflow-hidden">
                <div class="border-b border-line-soft px-4 py-3">
                    <h2 class="text-base font-bold">{{ __('system.backups.title') }}</h2>
                    <p class="text-sm text-muted">{{ __('system.backups.intro', ['keep' => config('manutrack.backups.keep')]) }}</p>
                </div>
                @forelse($backups as $b)
                    <div class="flex items-center justify-between gap-3 border-b border-line-soft px-4 py-2.5 text-sm last:border-0">
                        <span class="code min-w-0 truncate">{{ $b['name'] }}</span>
                        <span class="num shrink-0 text-muted">{{ \Illuminate\Support\Number::fileSize($b['size']) }}</span>
                    </div>
                @empty
                    <p class="p-4 text-muted">{{ __('system.backups.empty') }}</p>
                @endforelse
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <p class="font-mono text-xs text-muted">{{ __('system.backups.restore_hint') }}</p>
                    @can('system.update')
                        <form method="POST" action="{{ route('system.backup') }}" x-data="{ busy: false }" @submit="busy = true">@csrf<button class="btn btn-secondary btn-sm" :disabled="busy"><x-icon name="database" />{{ __('system.actions.backup_now') }}</button></form>
                    @endcan
                </div>
            </section>

            <section class="card overflow-hidden">
                <h2 class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('system.checks.title') }}</h2>
                <ul>
                    @foreach($checks as $check)
                        <li class="flex items-center justify-between gap-3 border-b border-line-soft px-4 py-2 text-sm last:border-0">
                            <span class="min-w-0">{{ $check['label'] }} <span class="block text-xs text-muted">{{ $check['detail'] }}</span></span>
                            <x-status :tone="$checkTone[$check['status']]" :icon="$checkIcon[$check['status']]">{{ __('system.checks.'.$check['status']) }}</x-status>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
</x-layouts.app>
