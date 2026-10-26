<x-layouts.guest :title="__('auth_ui.confirm.title')">
    <div class="card flex flex-col gap-5 p-6">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-bold">{{ __('auth_ui.confirm.title') }}</h1>
            <p class="text-fg-2">{{ __('auth_ui.confirm.intro') }}</p>
        </div>
        <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-4">
            @csrf
            <x-form.input name="password" type="password" :label="__('auth_ui.password')" autocomplete="current-password" autofocus required />
            <button type="submit" class="btn btn-primary btn-xl">{{ __('auth_ui.confirm.submit') }}</button>
        </form>
    </div>
</x-layouts.guest>
