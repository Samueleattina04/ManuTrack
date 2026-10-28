{{-- Sezioni di "Dati e privacy" --}}
@props(['current'])
<nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" aria-label="{{ __('gdpr.title') }}">
    @foreach(['exports' => 'download', 'anonymize' => 'user', 'retention' => 'clock', 'erase' => 'trash'] as $key => $icon)
        <a href="{{ route('gdpr.'.$key) }}" @class(['status-filter', 'is-active' => $current === $key]) @if($current === $key) aria-current="page" @endif><x-icon :name="$icon" class="!h-4 !w-4" />{{ __('gdpr.tabs.'.$key) }}</a>
    @endforeach
</nav>
