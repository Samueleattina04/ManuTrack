<x-layouts.guest :title="__('install.done_title')" :brand="false">
    <div class="card flex flex-col gap-5 p-6">
        <x-logo variant="horizontal" class="h-8 w-auto self-start" />
        <x-status tone="ok" icon="check-circle" class="self-start">{{ __('install.done_title') }}</x-status>
        <h1 class="text-3xl font-bold">{{ __('install.done_title') }}</h1>
        <p class="text-fg-2">{{ __('install.done_text', ['version' => $version]) }}</p>

        @if($tokenRemoved)
            <div class="alert alert-ok"><x-icon name="lock" /><span>{{ __('install.done_token_removed') }}</span></div>
        @else
            <div class="alert alert-warn"><x-icon name="lock" /><span>{{ __('install.done_token_manual') }}</span></div>
        @endif
        @if($vapid === false)
            <div class="alert alert-warn"><x-icon name="key" /><span>{{ __('install.vapid_manual') }} php artisan manutrack:vapid</span></div>
        @endif

        <div class="flex flex-col gap-2">
            <h2 class="text-lg font-bold">{{ __('install.next.title') }}</h2>
            <ul class="flex list-disc flex-col gap-1 pl-5 text-fg-2">
                <li>{{ __('install.next.cron') }}</li>
                <li>{{ __('install.next.https') }}</li>
            </ul>
        </div>

        <a href="{{ route('login') }}" class="btn btn-primary btn-xl"><x-icon name="arrow-right" />{{ __('install.done_go') }}</a>
    </div>
</x-layouts.guest>
