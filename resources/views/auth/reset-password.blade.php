<x-layouts.guest :title="__('auth_ui.reset.title')">
    <div class="card flex flex-col gap-5 p-6">
        <h1 class="text-2xl font-bold">{{ __('auth_ui.reset.title') }}</h1>
        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <x-form.input name="email" type="email" :label="__('auth_ui.email')" :value="$request->email" autocomplete="username" required />
            <x-form.input name="password" type="password" :label="__('auth_ui.new_password')" :hint="__('install.password_hint')" autocomplete="new-password" required />
            <x-form.input name="password_confirmation" type="password" :label="__('auth_ui.confirm_password')" autocomplete="new-password" required />
            <button type="submit" class="btn btn-primary btn-xl">{{ __('auth_ui.reset.submit') }}</button>
        </form>
    </div>
</x-layouts.guest>
