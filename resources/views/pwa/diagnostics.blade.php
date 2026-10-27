@php
    $texts = [
        'checks' => __('pwa.diag.checks'), 'values' => __('pwa.diag.values'), 'hints' => __('pwa.diag.hints'),
        'enabled' => __('pwa.wizard.notify_on'), 'copied' => __('pwa.diag.report_copied'),
    ];
@endphp
<x-layouts.app :title="__('pwa.diag.title')" :heading="__('pwa.diag.title')">
    <div class="mx-auto flex max-w-5xl flex-col gap-5"
         x-data="pushDiagnostics(@js(['texts' => $texts, 'hashes' => $endpointHashes, 'pushConfigured' => $pushConfigured, 'appUrl' => $appUrl]))">
        <p class="max-w-prose text-fg-2">{{ __('pwa.diag.intro') }}</p>

        <section class="card overflow-hidden" aria-labelledby="d-device">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line-soft px-4 py-3">
                <h2 id="d-device" class="text-base font-bold">{{ __('pwa.diag.this_device') }}</h2>
                <span class="text-sm text-muted" x-text="device"></span>
            </div>
            <ul>
                <template x-for="c in checks" :key="c.key">
                    <li class="flex items-start justify-between gap-3 border-b border-line-soft px-4 py-2.5 last:border-0">
                        <div class="min-w-0">
                            <div class="text-sm font-medium" x-text="c.label"></div>
                            <div class="text-xs text-muted" x-show="c.hint" x-text="c.hint"></div>
                        </div>
                        <span class="status shrink-0" :class="{ ok: 'status-ok', ko: 'status-ko', warn: 'status-warn', info: 'status-stop' }[c.state]">
                            <span class="h-2 w-2 rounded-full bg-current" aria-hidden="true"></span><span x-text="c.value"></span>
                        </span>
                    </li>
                </template>
            </ul>
            <div class="flex flex-wrap gap-2 border-t border-line-soft p-4">
                <button type="button" class="btn btn-primary" @click="enable()" :disabled="busy"><x-icon name="bell" />{{ __('pwa.diag.enable') }}</button>
                <button type="button" class="btn btn-secondary" @click="test(true)" :disabled="busy">{{ __('pwa.diag.test_this') }}</button>
                <button type="button" class="btn btn-secondary" @click="test(false)" :disabled="busy">{{ __('pwa.diag.test_all') }}</button>
                <button type="button" class="btn btn-ghost" @click="disable()" :disabled="busy">{{ __('pwa.diag.disable') }}</button>
                <button type="button" class="btn btn-ghost" @click="copyReport()"><x-icon name="clipboard-list" />{{ __('pwa.diag.copy_report') }}</button>
            </div>
        </section>

        <div x-show="message" x-cloak class="alert" :class="messageTone === 'ok' ? 'alert-ok' : (messageTone === 'info' ? '' : 'alert-ko')" role="status"><x-icon name="info" /><span x-text="message" class="break-words"></span></div>

        <section class="card overflow-hidden" aria-labelledby="d-list">
            <h2 id="d-list" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('pwa.diag.devices') }}</h2>
            @forelse($devices as $d)
                <div class="flex flex-wrap items-center gap-3 border-b border-line-soft px-4 py-3 last:border-0" x-data="{ gone: false }" x-show="! gone">
                    <x-icon :name="in_array($d->platform, ['ios', 'android', 'ipados']) ? 'smartphone' : 'monitor'" class="text-fg-2" />
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ $d->device_label ?? __('pwa.diag.device') }}
                            <span x-show="currentHash === @js($d->endpoint_hash)" class="status status-accent ml-1 !py-0 text-xs">{{ __('pwa.diag.current') }}</span>
                        </div>
                        <div class="text-xs text-muted">
                            {{ __('pwa.diag.service') }}: {{ $d->service() }} · {{ __('pwa.diag.since') }} {{ $d->created_at->format('d/m/Y') }} ·
                            {{ __('pwa.diag.last_success') }}: {{ $d->last_success_at?->diffForHumans() ?? __('pwa.diag.never') }}
                            @if($d->failed_count) · <span class="text-ko">{{ __('pwa.diag.errors') }}: {{ $d->failed_count }}{{ $d->last_error ? ' ('.$d->last_error.')' : '' }}</span>@endif
                        </div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm text-ko"
                            @click="if (confirm(@js(__('pwa.diag.remove_confirm')))) { $el.disabled = true; window.fetch(@js(route('push.devices.remove', $d)), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' } }).then(r => { if (r.ok) gone = true; }) }">
                        {{ __('pwa.diag.remove') }}
                    </button>
                </div>
            @empty
                <p class="p-4 text-muted">{{ __('pwa.diag.devices_empty') }}</p>
            @endforelse
        </section>

        <a href="{{ route('pwa.install') }}" class="btn btn-secondary self-start"><x-icon name="smartphone" />{{ __('pwa.diag.open_wizard') }}</a>
    </div>
</x-layouts.app>
