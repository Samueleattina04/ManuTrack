<x-layouts.guest :title="__('auth_ui.forgot.title')">
    <div class="card flex flex-col gap-5 p-6">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-bold">{{ __('auth_ui.forgot.title') }}</h1>
            <p class="text-fg-2">{{ __('auth_ui.forgot.intro') }}</p>
        </div>
        @if(session('status'))
            <div class="alert alert-ok" role="status"><x-icon name="check-circle" /><span>{{ session('status') }}</span></div>
        @endif
        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
            @csrf
            <x-form.input name="email" type="email" :label="__('auth_ui.email')" autocomplete="username" inputmode="email" autofocus required />
            <button type="submit" class="btn btn-primary btn-xl">{{ __('auth_ui.forgot.submit') }}</button>
        </form>
        <a href="{{ route('login') }}" class="text-center text-sm font-medium text-accent-fg underline-offset-4 hover:underline">{{ __('auth_ui.back_to_login') }}</a>
    </div>
</x-layouts.guest>
