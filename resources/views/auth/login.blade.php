<x-layouts.guest :title="__('auth_ui.login.title')">
    <div class="card flex flex-col gap-5 p-6">
        <h1 class="text-center text-2xl font-bold">{{ __('auth_ui.login.title') }}</h1>

        @if(session('status'))
            <div class="alert alert-ok" role="status"><x-icon name="check-circle" /><span>{{ session('status') }}</span></div>
        @endif

        @if(\App\Support\Demo\Edition::isDemo())
            <div class="alert alert-info" role="note"><x-icon name="info" /><span>{{ __('demo.banner', ['time' => config('manutrack.demo_reset_at')]) }} {{ __('demo.login_hint', ['email' => \Database\Seeders\DatabaseSeeder::DEMO_EMAIL, 'password' => \Database\Seeders\DatabaseSeeder::DEMO_PASSWORD]) }}</span></div>
        @endif
        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <x-form.input name="email" type="email" :label="__('auth_ui.email')" autocomplete="username" inputmode="email" autofocus required />
            <x-form.input name="password" type="password" :label="__('auth_ui.password')" autocomplete="current-password" required />
            <div class="flex items-center justify-between gap-3">
                <label class="flex min-h-[44px] cursor-pointer items-center gap-2.5 text-sm">
                    <input type="checkbox" name="remember" value="1" class="h-5 w-5 accent-[var(--mt-accent)]" @checked(old('remember'))>
                    {{ __('auth_ui.login.remember') }}
                </label>
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-accent-fg underline-offset-4 hover:underline">{{ __('auth_ui.login.forgot') }}</a>
            </div>
            <button type="submit" class="btn btn-primary btn-xl" :disabled="busy"><x-icon name="arrow-right" />{{ __('auth_ui.login.submit') }}</button>
        </form>
        <p class="text-center text-sm text-muted">{{ __('auth_ui.login.invite_only') }}</p>
    </div>
</x-layouts.guest>
