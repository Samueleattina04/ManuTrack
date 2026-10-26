@php
    // Avviso su aggiornamento fallito o in attesa: solo per chi gestisce il sistema
    $show = auth()->user()?->can('system.view');
    $error = $show ? settings(\App\Support\Update\Updater::ERROR_SETTING) : null;
    $pending = $show && ! $error && \App\Support\AppVersion::updatePending();
@endphp
@if($error || $pending)
    <div class="app-content !pb-0 pt-4">
        @if($error)
            <div class="alert alert-ko mx-auto max-w-6xl" role="alert">
                <x-icon name="alert-triangle" />
                <div class="min-w-0 flex-1">
                    <strong>{{ __('system.banner.failed_title', ['version' => $error['version'] ?? '']) }}</strong>
                    <p class="text-fg-2">{{ __('system.banner.failed_text') }}</p>
                </div>
                <a href="{{ route('system') }}" class="btn btn-secondary btn-sm">{{ __('system.banner.details') }}</a>
            </div>
        @else
            <div class="alert alert-warn mx-auto max-w-6xl" role="status">
                <x-icon name="refresh" />
                <div class="min-w-0 flex-1">
                    <strong>{{ __('system.banner.pending_title', ['version' => manutrack_version()]) }}</strong>
                    <p class="text-fg-2">{{ __('system.banner.pending_text') }}</p>
                </div>
            </div>
        @endif
    </div>
@endif
