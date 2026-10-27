<x-layouts.app :title="__('pwa.wizard.title')" :heading="__('pwa.wizard.title')">
    <div class="mx-auto flex max-w-4xl flex-col gap-5"
         x-data="installWizard(@js(['pushConfigured' => $pushConfigured, 'errorText' => __('pwa.wizard.error')]))">
        <div class="flex flex-col gap-2">
            <h2 class="text-2xl font-bold">{{ __('pwa.wizard.heading') }}</h2>
            <p class="max-w-prose text-fg-2">{{ __('pwa.wizard.intro') }}</p>
            <p class="text-sm text-muted">{{ __('pwa.wizard.detected') }}: <span class="font-medium text-fg-2" x-text="device"></span></p>
        </div>

        {{-- Avanzamento --}}
        <ol class="grid grid-cols-3 gap-2" aria-label="{{ __('pwa.wizard.title') }}">
            @foreach(['install', 'notify', 'test'] as $i => $s)
                <li class="flex flex-col items-center gap-1 rounded-[var(--radius-md)] border px-2 py-2 text-center text-[13px] font-semibold sm:flex-row sm:gap-2 sm:px-3 sm:text-left sm:text-sm"
                    :class="step === '{{ $s }}' ? 'border-accent-fg bg-surface-2 text-fg' : ({ install: 0, notify: 1, test: 2 }[step] > {{ $i }} ? 'border-line-soft text-ok' : 'border-line-soft text-muted')">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-bg-2 text-xs" x-text="{ install: 0, notify: 1, test: 2 }[step] > {{ $i }} ? '✓' : '{{ $i + 1 }}'"></span>
                    <span>{{ __('pwa.wizard.steps.'.$s) }}</span>
                </li>
            @endforeach
        </ol>

        {{-- 1. Installazione --}}
        <section x-show="step === 'install'" x-cloak class="flex flex-col gap-4" aria-labelledby="w-install">
            <template x-if="env.platform === 'ios'">
                <div class="flex flex-col gap-4">
                    <h3 id="w-install" class="text-lg font-bold">{{ __('pwa.wizard.ios_title') }}</h3>
                    <div class="alert"><x-icon name="info" /><span>{{ __('pwa.wizard.ios_why') }}</span></div>
                    <div x-show="env.iosTooOld" class="alert alert-warn"><x-icon name="alert-triangle" /><span x-text="@js(__('pwa.wizard.ios_old')).replace(':version', env.iosVersion)"></span></div>
                    <div x-show="! env.isSafari" class="card flex flex-col gap-3 p-4">
                        <p class="text-sm">{{ __('pwa.wizard.ios_other_browser') }}</p>
                        <div class="flex gap-2"><input x-ref="link" class="input font-mono text-sm" :value="url" readonly aria-label="URL"><button type="button" class="btn btn-secondary" @click="copy()" x-text="copied ? @js(__('pwa.wizard.copied')) : @js(__('pwa.wizard.copy_link'))"></button></div>
                    </div>
                    <x-pwa-steps platform="ios" />
                </div>
            </template>
            <template x-if="env.platform === 'android'">
                <div class="flex flex-col gap-4">
                    <h3 class="text-lg font-bold">{{ __('pwa.wizard.android_title') }}</h3>
                    <button type="button" class="btn btn-primary btn-xl" x-show="installAvailable" @click="install()"><x-icon name="download" />{{ __('pwa.wizard.android_button') }}</button>
                    <x-pwa-steps platform="android" />
                </div>
            </template>
            <button type="button" class="btn btn-ghost self-start" @click="installSkipped = true">{{ __('pwa.wizard.skip_install') }} <x-icon name="arrow-right" class="!w-4 !h-4" /></button>
        </section>

        {{-- PC: installazione facoltativa --}}
        <div x-show="env.platform === 'desktop' && step !== 'install' && ! env.standalone" x-cloak class="card flex flex-wrap items-center gap-4 p-4">
            <x-icon name="monitor" class="!h-8 !w-8 text-accent-fg" />
            <div class="min-w-0 flex-1"><strong>{{ __('pwa.wizard.desktop_title') }}</strong><p class="text-sm text-fg-2">{{ __('pwa.wizard.desktop_text') }}</p></div>
            <button type="button" class="btn btn-secondary" x-show="installAvailable" @click="install()"><x-icon name="download" />{{ __('pwa.wizard.desktop_button') }}</button>
        </div>
        <div x-show="env.standalone" x-cloak class="alert alert-ok"><x-icon name="check-circle" /><span>{{ __('pwa.wizard.installed') }}</span></div>

        {{-- 2. Notifiche --}}
        <section x-show="step === 'notify'" x-cloak class="card flex flex-col gap-4 p-5" aria-labelledby="w-notify">
            <div class="flex items-center gap-3"><x-icon name="bell" class="!h-8 !w-8 text-accent-fg" /><h3 id="w-notify" class="text-lg font-bold">{{ __('pwa.wizard.notify_title') }}</h3></div>
            <p class="text-fg-2" x-show="! notifyBlocker">{{ __('pwa.wizard.notify_text') }}</p>
            <div x-show="notifyBlocker === 'need_install'" class="alert alert-warn"><x-icon name="smartphone" /><span>{{ __('pwa.wizard.notify_need_install') }}</span></div>
            <div x-show="notifyBlocker === 'unsupported'" class="alert alert-warn"><x-icon name="info" /><span>{{ __('pwa.wizard.notify_unsupported') }}</span></div>
            <div x-show="notifyBlocker === 'insecure'" class="alert alert-ko"><x-icon name="lock" /><span>{{ __('pwa.wizard.notify_insecure') }}</span></div>
            <div x-show="notifyBlocker === 'no_keys'" class="alert alert-ko"><x-icon name="key" /><span>{{ __('pwa.wizard.notify_no_keys') }}</span></div>
            <div x-show="notifyBlocker === 'ios_old'" class="alert alert-warn"><x-icon name="alert-triangle" /><span x-text="@js(__('pwa.wizard.ios_old')).replace(':version', env.iosVersion)"></span></div>
            <div x-show="notifyBlocker === 'denied'" class="alert alert-ko"><x-icon name="x-circle" />
                <div><strong>{{ __('pwa.wizard.denied_title') }}</strong>
                    <p x-show="env.platform === 'ios'">{{ __('pwa.wizard.denied_ios') }}</p>
                    <p x-show="env.platform === 'android'">{{ __('pwa.wizard.denied_android') }}</p>
                    <p x-show="env.platform === 'desktop'">{{ __('pwa.wizard.denied_desktop') }}</p>
                </div>
            </div>
            {{-- Il permesso si chiede solo da qui, con il tocco sul pulsante --}}
            <button type="button" class="btn btn-primary btn-xl" @click="enable()" :disabled="busy || ! canNotify || env.permission === 'denied'"><x-icon name="bell" />{{ __('pwa.wizard.notify_button') }}</button>
        </section>

        {{-- 3. Prova --}}
        <section x-show="step === 'test'" x-cloak class="card flex flex-col gap-4 p-5" aria-labelledby="w-test">
            <div class="alert alert-ok"><x-icon name="check-circle" /><span>{{ __('pwa.wizard.notify_on') }}</span></div>
            <h3 id="w-test" class="text-lg font-bold">{{ __('pwa.wizard.test_title') }}</h3>
            <p class="text-fg-2">{{ __('pwa.wizard.test_text') }}</p>
            <button type="button" class="btn btn-primary btn-xl" @click="test()" :disabled="busy"><x-icon name="bell" />{{ __('pwa.wizard.test_button') }}</button>
        </section>

        <div x-show="message" x-cloak class="alert" :class="messageTone === 'ok' ? 'alert-ok' : 'alert-ko'" role="status"><x-icon name="info" /><span x-text="message"></span></div>
        <a href="{{ route('pwa.diagnostics') }}" class="self-start text-sm font-medium text-accent-fg underline-offset-4 hover:underline">{{ __('pwa.wizard.not_arrived') }}</a>
    </div>
</x-layouts.app>
