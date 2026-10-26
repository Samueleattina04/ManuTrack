<x-layouts.guest :title="__('auth_ui.two_factor.title')">
    <div class="card flex flex-col gap-5 p-6" x-data="{ recovery: false }">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-bold">{{ __('auth_ui.two_factor.title') }}</h1>
            <p class="text-fg-2" x-show="! recovery">{{ __('auth_ui.two_factor.intro_code') }}</p>
            <p class="text-fg-2" x-show="recovery" x-cloak>{{ __('auth_ui.two_factor.intro_recovery') }}</p>
        </div>
        <form method="POST" action="{{ route('two-factor.login') }}" class="flex flex-col gap-4">
            @csrf
            <div x-show="! recovery">
                <x-form.input name="code" :label="__('auth_ui.two_factor.code')" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" autofocus />
            </div>
            <div x-show="recovery" x-cloak>
                <x-form.input name="recovery_code" :label="__('auth_ui.two_factor.recovery_code')" autocomplete="off" />
            </div>
            <button type="submit" class="btn btn-primary btn-xl">{{ __('auth_ui.two_factor.submit') }}</button>
        </form>
        <button type="button" class="btn btn-ghost" @click="recovery = ! recovery">
            <span x-show="! recovery">{{ __('auth_ui.two_factor.use_recovery') }}</span>
            <span x-show="recovery" x-cloak>{{ __('auth_ui.two_factor.use_code') }}</span>
        </button>
    </div>
</x-layouts.guest>
