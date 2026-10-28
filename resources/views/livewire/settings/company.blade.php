@php
    $fmt = fn (float $v) => number_format($v, 1, ',', '');
    if (! in_array($timezone, $timezones, true)) { array_unshift($timezones, $timezone); }
@endphp
<div class="mx-auto flex max-w-5xl flex-col gap-6">
    @if(session('status'))
        <div class="alert alert-ok" role="status"><x-icon name="check-circle" /><span>{{ session('status') }}</span></div>
    @endif

    {{-- Logo del cliente --}}
    <section class="card flex flex-col gap-4 p-5" aria-labelledby="s-logo">
        <div>
            <h2 id="s-logo" class="text-lg font-bold">{{ __('settings.logo.title') }}</h2>
            <p class="text-sm text-fg-2">{{ __('settings.logo.intro') }}</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            {{-- Versione principale (per fondi chiari) --}}
            <div class="flex flex-col gap-3">
                <span class="field-label">{{ __('settings.logo.light') }}</span>
                <div class="grid h-28 place-items-center rounded-[var(--radius-md)] border border-line-soft bg-white p-4">
                    @if($logoUrl)<img src="{{ $logoUrl }}" alt="{{ __('settings.logo.current') }}" class="max-h-16 w-auto">@else<span class="text-sm text-[#5b6472]">{{ __('settings.logo.none') }}</span>@endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <label class="btn btn-secondary cursor-pointer">
                        <x-icon name="upload" />{{ $logoUrl ? __('settings.logo.replace') : __('settings.logo.upload') }}
                        <input type="file" class="sr-only" wire:model="logo" accept="image/png,image/jpeg,image/webp">
                    </label>
                    @if($logoUrl)<button type="button" class="btn btn-ghost" wire:click="removeLogo('chiaro')" wire:confirm="{{ __('settings.logo.remove_confirm') }}">{{ __('settings.logo.remove') }}</button>@endif
                </div>
                <span wire:loading wire:target="logo" class="text-sm text-muted">{{ __('settings.logo.uploading') }}</span>
                @error('logo')<span class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $message }}</span>@enderror
            </div>
            {{-- Versione per il tema scuro (facoltativa) --}}
            <div class="flex flex-col gap-3">
                <span class="field-label">{{ __('settings.logo.dark') }}</span>
                <div class="grid h-28 place-items-center rounded-[var(--radius-md)] border border-line-soft bg-[#151a21] p-4">
                    @if($logoDarkUrl)
                        <img src="{{ $logoDarkUrl }}" alt="{{ __('settings.logo.current_dark') }}" class="max-h-16 w-auto">
                    @elseif($logoUrl)
                        <span class="rounded-[10px] bg-[#f4f5f7] px-3 py-2"><img src="{{ $logoUrl }}" alt="" class="max-h-12 w-auto"></span>
                    @else
                        <span class="text-sm text-[#8c95a3]">{{ __('settings.logo.none') }}</span>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <label class="btn btn-secondary cursor-pointer">
                        <x-icon name="upload" />{{ $logoDarkUrl ? __('settings.logo.replace') : __('settings.logo.upload') }}
                        <input type="file" class="sr-only" wire:model="logoDark" accept="image/png,image/jpeg,image/webp">
                    </label>
                    @if($logoDarkUrl)<button type="button" class="btn btn-ghost" wire:click="removeLogo('scuro')">{{ __('settings.logo.remove') }}</button>@endif
                </div>
                <span class="field-hint">{{ __('settings.logo.dark_hint') }}</span>
                @error('logoDark')<span class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $message }}</span>@enderror
            </div>
        </div>
        <p class="text-xs text-muted">{{ __('settings.logo.rules') }}@if(! $imageDriver) {{ __('settings.logo.no_driver') }}@endif</p>
    </section>

    <form wire:submit="save" class="flex flex-col gap-6">
        {{-- Dati dell'azienda --}}
        <section class="card flex flex-col gap-4 p-5" aria-labelledby="s-company">
            <h2 id="s-company" class="text-lg font-bold">{{ __('settings.company.section') }}</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="field md:col-span-1">
                    <label for="c-name" class="field-label">{{ __('settings.company.name') }}</label>
                    <input id="c-name" wire:model="name" class="input" autocomplete="organization" @error('name') aria-invalid="true" @enderror required>
                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="c-tz" class="field-label">{{ __('settings.company.timezone') }}</label>
                    <select id="c-tz" wire:model="timezone" class="input">@foreach($timezones as $tz)<option value="{{ $tz }}">{{ str_replace('_', ' ', $tz) }}</option>@endforeach</select>
                </div>
                <div class="field">
                    <label for="c-locale" class="field-label">{{ __('settings.company.locale') }}</label>
                    <select id="c-locale" wire:model="locale" class="input">@foreach($locales as $l)<option value="{{ $l }}">{{ __('settings.locales.'.$l) }}</option>@endforeach</select>
                </div>
            </div>
        </section>

        {{-- Colori --}}
        <section class="card flex flex-col gap-4 p-5" aria-labelledby="s-colors">
            <div>
                <h2 id="s-colors" class="text-lg font-bold">{{ __('settings.colors.title') }}</h2>
                <p class="text-sm text-fg-2">{{ __('settings.colors.intro') }}</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5" role="radiogroup" aria-label="{{ __('settings.colors.title') }}">
                @foreach($palettes as $key => $p)
                    <label class="flex min-h-[56px] cursor-pointer items-center gap-3 rounded-[var(--radius-md)] border border-line bg-bg-2 px-3 has-[:checked]:border-accent-fg has-[:checked]:bg-surface-2">
                        <input type="radio" wire:model.live="palette" value="{{ $key }}" class="h-5 w-5 accent-[var(--mt-accent)]">
                        <span class="flex -space-x-1" aria-hidden="true"><span class="h-5 w-5 rounded-full border-2 border-surface" style="background: {{ $p['logo_light_d'] }}"></span><span class="h-5 w-5 rounded-full border-2 border-surface" style="background: {{ $p['accent_l'] }}"></span></span>
                        <span class="text-sm font-medium">{{ $p['label'] }}</span>
                    </label>
                @endforeach
                <label class="flex min-h-[56px] cursor-pointer items-center gap-3 rounded-[var(--radius-md)] border border-line bg-bg-2 px-3 has-[:checked]:border-accent-fg has-[:checked]:bg-surface-2">
                    <input type="radio" wire:model.live="palette" value="custom" class="h-5 w-5 accent-[var(--mt-accent)]">
                    <x-icon name="palette" />
                    <span class="text-sm font-medium">{{ __('settings.colors.custom') }}</span>
                </label>
            </div>

            @if($palette === 'custom')
                <div class="grid gap-4 rounded-[var(--radius-md)] border border-line-soft bg-bg-2 p-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                    <div class="field">
                        <label for="c-accent" class="field-label">{{ __('settings.colors.accent') }}</label>
                        <div class="flex gap-2">
                            <input type="color" wire:model.live="customAccent" class="h-11 w-14 cursor-pointer rounded-[var(--radius-md)] border border-line bg-transparent" aria-label="{{ __('settings.colors.accent') }}">
                            <input id="c-accent" wire:model.live.debounce.400ms="customAccent" class="input font-mono" maxlength="7">
                        </div>
                        <span class="field-hint">{{ __('settings.colors.accent_hint') }}</span>
                        @error('customAccent')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="c-soft" class="field-label">{{ __('settings.colors.soft') }}</label>
                        <div class="flex gap-2">
                            <input type="color" wire:model.live="customSoft" class="h-11 w-14 cursor-pointer rounded-[var(--radius-md)] border border-line bg-transparent" aria-label="{{ __('settings.colors.soft') }}">
                            <input id="c-soft" wire:model.live.debounce.400ms="customSoft" class="input font-mono" maxlength="7">
                        </div>
                        <span class="field-hint">{{ __('settings.colors.soft_hint') }}</span>
                        @error('customSoft')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <button type="button" class="btn btn-secondary" wire:click="sampleFromLogo" @disabled(! $logoUrl)><x-icon name="sparkles" />{{ __('settings.colors.sample') }}</button>
                </div>
            @endif

            @if($preview)
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach(['d' => ['#0c0f14', '#151a21', '#e8ebf0', '#2fd3b0', __('app.theme.dark')], 'l' => ['#edeff2', '#ffffff', '#10141a', '#0a7563', __('app.theme.light')]] as $t => [$bg, $surface, $fg, $ok, $label])
                        @php($fgAcc = $preview['accent_fg_'.$t] ?? $preview['accent_'.$t])
                        <div class="flex flex-col gap-3 rounded-[var(--radius-lg)] border border-line p-4" style="background: {{ $bg }}; color: {{ $fg }}">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-[11px] uppercase tracking-wider opacity-70">{{ $label }}</span>
                                <svg viewBox="0 0 406 378" class="h-7 w-auto" aria-hidden="true"><polygon points="0,0 203,202 203,339 90,227 86,377 0,292" fill="{{ $preview['logo_light_'.$t] }}"/><polygon points="406,0 203,202 203,339 316,227 320,377 406,292" fill="{{ $preview['logo_dark_'.$t] }}"/></svg>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="btn btn-sm" style="background: {{ $preview['accent_'.$t] }}; color: {{ $preview['on_accent_'.$t] }}">{{ __('settings.colors.sample_button') }}</span>
                                <span class="status" style="--c: {{ $ok }}"><x-icon name="check-circle" />{{ __('enums.asset_status.operativa') }}</span>
                                <span class="text-sm font-semibold" style="color: {{ $fgAcc }}">{{ __('settings.colors.sample_text') }}</span>
                            </div>
                            <div class="text-xs opacity-80 num">
                                {{ __('settings.colors.contrast_button', ['value' => $fmt($contrast['button_'.($t === 'd' ? 'dark' : 'light')])]) }} ·
                                {{ __('settings.colors.contrast_text', ['value' => $fmt($contrast['text_'.($t === 'd' ? 'dark' : 'light')])]) }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-muted">{{ __('settings.colors.aa_note') }}</p>
            @endif
        </section>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><x-icon name="check" />{{ __('settings.save') }}</button>
        </div>
    </form>

    @if($isAdmin)
        <section class="card flex flex-col gap-4 p-5" aria-labelledby="s-2fa">
            <h2 id="s-2fa" class="text-lg font-bold">{{ __('settings.two_factor.title') }}</h2>
            <p class="max-w-prose text-sm text-fg-2">{{ __('settings.two_factor.intro') }}</p>
            <label class="flex items-start gap-3">
                <input type="checkbox" class="mt-1" wire:model="adminTwoFactor">
                <span>{{ __('settings.two_factor.require') }}</span>
            </label>
            @error('adminTwoFactor')<span class="field-error"><x-icon name="alert-circle" class="!h-4 !w-4" />{{ $message }}</span>@enderror
            @if($adminsWithout !== [])
                <div class="alert alert-warn" role="status"><x-icon name="alert-triangle" /><span>{{ __('settings.two_factor.without', ['names' => implode(', ', $adminsWithout)]) }}</span></div>
            @endif
            <div><button type="button" class="btn btn-secondary" wire:click="saveTwoFactor"><x-icon name="shield" />{{ __('settings.save') }}</button></div>
        </section>
    @endif
</div>
