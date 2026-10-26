@php
    $tone = ['ok' => 'ok', 'warning' => 'warn', 'error' => 'ko'];
    $icon = ['ok' => 'check-circle', 'warning' => 'alert-triangle', 'error' => 'x-circle'];
    $problems = collect($checks)->where('status', '!=', 'ok');
@endphp
<x-layouts.guest :title="__('install.title')">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <span class="eyebrow">ManuTrack {{ manutrack_version() }}</span>
            <h1 class="text-3xl font-bold">{{ __('install.heading') }}</h1>
            <p class="text-fg-2">{{ __('install.intro') }}</p>
        </div>

        <details class="card" @if(! $passes) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4">
                <span class="font-semibold">{{ __('install.requirements') }}</span>
                @if($passes && $problems->isEmpty())
                    <x-status tone="ok" icon="check-circle">OK</x-status>
                @elseif($passes)
                    <x-status tone="warn" icon="alert-triangle">{{ $problems->count() }}</x-status>
                @else
                    <x-status tone="ko" icon="x-circle">{{ $problems->where('status', 'error')->count() }}</x-status>
                @endif
            </summary>
            <ul class="border-t border-line-soft">
                @foreach($checks as $check)
                    <li class="flex items-center justify-between gap-3 border-b border-line-soft px-4 py-2 text-sm last:border-0">
                        <span class="min-w-0">{{ $check['label'] }}<span class="block text-xs text-muted">{{ $check['detail'] }}</span></span>
                        <x-status :tone="$tone[$check['status']]" :icon="$icon[$check['status']]">{{ __('system.checks.'.$check['status']) }}</x-status>
                    </li>
                @endforeach
            </ul>
        </details>

        @if(! $passes)
            <div class="alert alert-ko" role="alert"><x-icon name="x-circle" /><span>{{ __('install.requirements_failed') }}</span></div>
        @elseif(! $tokenConfigured)
            <div class="alert alert-warn" role="alert"><x-icon name="lock" /><span>{{ __('install.token_missing') }}</span></div>
        @else
            @error('install')
                <div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ $message }}</span></div>
            @enderror

            <form method="POST" action="{{ route('install.store') }}" class="card flex flex-col gap-6 p-5" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <fieldset class="flex flex-col gap-4">
                    <legend class="mb-1 font-display text-lg font-bold">{{ __('install.section.security') }}</legend>
                    <x-form.input name="token" type="password" :label="__('install.field.token')" :hint="__('install.field.token_hint')" autocomplete="off" required />
                </fieldset>

                <fieldset class="flex flex-col gap-4">
                    <legend class="mb-1 font-display text-lg font-bold">{{ __('install.section.company') }}</legend>
                    <x-form.input name="company" :label="__('install.field.company')" autocomplete="organization" required />
                    <div class="field">
                        <span class="field-label" id="palette-label">{{ __('install.field.palette') }}</span>
                        <div class="grid gap-2 sm:grid-cols-3" role="radiogroup" aria-labelledby="palette-label">
                            @foreach($palettes as $key => $p)
                                <label class="flex min-h-[52px] cursor-pointer items-center gap-3 rounded-[var(--radius-md)] border border-line bg-bg-2 px-3 has-[:checked]:border-accent has-[:checked]:bg-surface-2">
                                    <input type="radio" name="palette" value="{{ $key }}" class="h-5 w-5 accent-[var(--mt-accent)]" @checked(old('palette', 'rame') === $key)>
                                    <span class="h-4 w-4 rounded-full" style="background: {{ $p['accent_d'] }}"></span>
                                    <span class="text-sm font-medium">{{ $p['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </fieldset>

                <fieldset class="flex flex-col gap-4">
                    <legend class="mb-1 font-display text-lg font-bold">{{ __('install.section.admin') }}</legend>
                    <x-form.input name="name" :label="__('install.field.name')" autocomplete="name" required />
                    <x-form.input name="email" type="email" :label="__('install.field.email')" autocomplete="email" inputmode="email" required />
                    <x-form.input name="password" type="password" :label="__('install.field.password')" :hint="__('install.password_hint')" autocomplete="new-password" required />
                    <x-form.input name="password_confirmation" type="password" :label="__('install.field.password_confirmation')" autocomplete="new-password" required />
                </fieldset>

                <fieldset class="flex flex-col gap-3">
                    <legend class="mb-1 font-display text-lg font-bold">{{ __('install.section.options') }}</legend>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="hidden" name="vapid" value="0">
                        <input type="checkbox" name="vapid" value="1" class="mt-0.5 h-5 w-5 accent-[var(--mt-accent)]" @checked(old('vapid', '1') === '1')>
                        <span><span class="font-medium">{{ __('install.field.vapid') }}</span><span class="block text-sm text-muted">{{ __('install.field.vapid_hint') }}</span></span>
                    </label>
                </fieldset>

                <button type="submit" class="btn btn-primary btn-xl" :aria-disabled="busy" :disabled="busy">
                    <x-icon name="arrow-right" />{{ __('install.submit') }}
                </button>
            </form>
        @endif
    </div>
</x-layouts.guest>
