<x-layouts.guest :title="__('users.invite.accept_title')">
    <div class="card flex flex-col gap-5 p-6">
        @if($invitation)
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-bold">{{ __('users.invite.accept_title') }}</h1>
                <p class="text-fg-2">{{ __('users.invite.accept_intro', ['company' => settings('company.name'), 'role' => __('access.roles.'.$invitation->role)]) }}</p>
            </div>
            <form method="POST" action="{{ route('invitation.accept', ['token' => $token]) }}" class="flex flex-col gap-4">
                @csrf
                <div class="field">
                    <span class="field-label">{{ __('auth_ui.email') }}</span>
                    <span class="input flex items-center text-fg-2">{{ $invitation->email }}</span>
                </div>
                <x-form.input name="name" :label="__('users.field.name')" :value="$invitation->name" autocomplete="name" required />
                <x-form.input name="password" type="password" :label="__('auth_ui.password')" :hint="__('install.password_hint')" autocomplete="new-password" required />
                <x-form.input name="password_confirmation" type="password" :label="__('auth_ui.confirm_password')" autocomplete="new-password" required />
                <button type="submit" class="btn btn-primary btn-xl">{{ __('users.invite.accept_submit') }}</button>
            </form>
        @else
            <x-status tone="warn" icon="alert-triangle" class="self-start">{{ __('users.invite.invalid_title') }}</x-status>
            <h1 class="text-2xl font-bold">{{ __('users.invite.invalid_title') }}</h1>
            <p class="text-fg-2">{{ __('users.invite.invalid_text') }}</p>
            <a href="{{ route('login') }}" class="btn btn-secondary">{{ __('auth_ui.back_to_login') }}</a>
        @endif
    </div>
</x-layouts.guest>
