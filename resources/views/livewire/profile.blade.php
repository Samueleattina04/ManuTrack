<div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_20rem]">
    <div class="flex flex-col gap-6">
        <form wire:submit="saveProfile" class="card flex flex-col gap-4 p-5" aria-labelledby="p-data">
            <h2 id="p-data" class="text-lg font-bold">{{ __('profile.data') }}</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="field"><label for="p-name" class="field-label">{{ __('users.field.name') }}</label><input id="p-name" wire:model="name" class="input" autocomplete="name" required>@error('name')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="p-email" class="field-label">{{ __('users.field.email') }}</label><input id="p-email" type="email" wire:model="email" class="input" autocomplete="email" required>@error('email')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="p-job" class="field-label">{{ __('users.field.job_title') }}</label><input id="p-job" wire:model="jobTitle" class="input" autocomplete="organization-title"></div>
                <div class="field"><label for="p-phone" class="field-label">{{ __('users.field.phone') }}</label><input id="p-phone" type="tel" wire:model="phone" class="input" autocomplete="tel" inputmode="tel"></div>
            </div>
            <div><button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('settings.save') }}</button></div>
        </form>

        <form wire:submit="changePassword" class="card flex flex-col gap-4 p-5" aria-labelledby="p-pass">
            <h2 id="p-pass" class="text-lg font-bold">{{ __('profile.password') }}</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="field"><label for="p-cur" class="field-label">{{ __('profile.current_password') }}</label><input id="p-cur" type="password" wire:model="currentPassword" class="input" autocomplete="current-password">@error('currentPassword')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="p-new" class="field-label">{{ __('auth_ui.new_password') }}</label><input id="p-new" type="password" wire:model="password" class="input" autocomplete="new-password">@error('password')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="p-conf" class="field-label">{{ __('auth_ui.confirm_password') }}</label><input id="p-conf" type="password" wire:model="password_confirmation" class="input" autocomplete="new-password"></div>
            </div>
            <p class="field-hint">{{ __('install.password_hint') }}</p>
            <div><button type="submit" class="btn btn-secondary"><x-icon name="key" />{{ __('profile.change_password') }}</button></div>
        </form>

        <section class="card flex flex-col gap-4 p-5" aria-labelledby="p-2fa">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="p-2fa" class="text-lg font-bold">{{ __('profile.two_factor.title') }}</h2>
                @if($user->hasTwoFactor())<x-status tone="ok" icon="shield">{{ __('profile.two_factor.on') }}</x-status>@else<x-status tone="warn" icon="alert-triangle">{{ __('profile.two_factor.off') }}</x-status>@endif
            </div>
            <p class="text-sm text-fg-2">{{ __('profile.two_factor.intro') }}</p>

            @if($pendingTwoFactor)
                <div class="grid gap-4 md:grid-cols-[auto_1fr] md:items-center">
                    <div class="w-44 rounded-[var(--radius-md)] bg-white p-3" aria-label="{{ __('profile.two_factor.qr') }}">{!! $user->twoFactorQrCodeSvg() !!}</div>
                    <div class="flex flex-col gap-3">
                        <p class="text-sm">{{ __('profile.two_factor.scan') }}</p>
                        <p class="text-xs text-muted">{{ __('profile.two_factor.manual') }} <span class="code break-all">{{ decrypt($user->two_factor_secret) }}</span></p>
                        <form wire:submit="confirmTwoFactor" class="flex flex-wrap items-end gap-2">
                            <div class="field"><label for="p-code" class="field-label">{{ __('auth_ui.two_factor.code') }}</label><input id="p-code" wire:model="twoFactorCode" class="input w-40 font-mono" inputmode="numeric" autocomplete="one-time-code"></div>
                            <button type="submit" class="btn btn-primary">{{ __('profile.two_factor.confirm') }}</button>
                        </form>
                        @error('twoFactorCode')<span class="field-error">{{ $message }}</span>@enderror
                        @error('code')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            @else
                <form class="flex flex-wrap items-end gap-2" @if($user->hasTwoFactor()) wire:submit="regenerateRecoveryCodes" @else wire:submit="enableTwoFactor" @endif>
                    <div class="field"><label for="p-2fa-pass" class="field-label">{{ __('profile.two_factor.password') }}</label><input id="p-2fa-pass" type="password" wire:model="twoFactorPassword" class="input w-56" autocomplete="current-password"></div>
                    @if($user->hasTwoFactor())
                        <button type="submit" class="btn btn-secondary">{{ __('profile.two_factor.new_codes') }}</button>
                        <button type="button" class="btn btn-ghost text-ko" wire:click="disableTwoFactor" wire:confirm="{{ __('profile.two_factor.disable_confirm') }}">{{ __('profile.two_factor.disable') }}</button>
                    @else
                        <button type="submit" class="btn btn-primary"><x-icon name="shield" />{{ __('profile.two_factor.enable') }}</button>
                    @endif
                </form>
                @error('twoFactorPassword')<span class="field-error">{{ $message }}</span>@enderror
            @endif

            @if($showRecoveryCodes && $user->hasTwoFactor())
                <div class="alert alert-warn"><x-icon name="key" />
                    <div class="flex flex-col gap-2"><strong>{{ __('profile.two_factor.codes_title') }}</strong><span>{{ __('profile.two_factor.codes_text') }}</span>
                        <ul class="grid grid-cols-2 gap-1 font-mono text-sm">@foreach($user->recoveryCodes() as $code)<li>{{ $code }}</li>@endforeach</ul>
                    </div>
                </div>
            @endif
        </section>
    </div>

    <aside class="flex flex-col gap-4">
        <div class="card flex flex-col items-center gap-3 p-5 text-center">
            <span class="grid h-16 w-16 place-items-center rounded-full bg-surface-2 font-display text-xl font-bold" aria-hidden="true">{{ $user->initials() }}</span>
            <div><div class="font-semibold">{{ $user->name }}</div><div class="text-sm text-muted">{{ $user->roleName() ? __('access.roles.'.$user->roleName()) : '' }}</div></div>
        </div>
        <div class="card flex flex-col gap-3 p-5">
            <h2 class="font-bold">{{ __('app.theme.label') }}</h2>
            <x-theme-switcher />
        </div>
        <a href="{{ route('pwa.install') }}" class="card flex items-center gap-3 p-4 hover:bg-surface-2"><x-icon name="smartphone" /><span class="flex-1 font-medium">{{ __('pwa.nav') }}</span><x-icon name="chevron-right" /></a>
        <a href="{{ route('pwa.diagnostics') }}" class="card flex items-center gap-3 p-4 hover:bg-surface-2"><x-icon name="bell" /><span class="flex-1 font-medium">{{ __('pwa.diag.title') }}</span><x-icon name="chevron-right" /></a>
        @can('system.view')
            <a href="{{ route('system') }}" class="card flex items-center gap-3 p-4 hover:bg-surface-2"><x-icon name="server" /><span class="flex-1 font-medium">{{ __('app.nav.system') }}</span><x-icon name="chevron-right" /></a>
        @endcan
        <form method="POST" action="{{ route('logout') }}" onsubmit="try { window.caches && caches.delete('mt-pages'); sessionStorage.removeItem('mt-warm'); } catch (e) {}">
            @csrf
            <button type="submit" class="btn btn-secondary w-full"><x-icon name="log-out" />{{ __('auth_ui.logout') }}</button>
        </form>
    </aside>
</div>
