@props(['tone' => 'stop', 'icon' => null, 'status' => null])
@php
    // Accetta un enum con label()/tone()/icon() oppure tono, icona e testo espliciti
    if ($status) {
        $tone = $status->tone();
        $icon = $status->icon();
    }
@endphp
<span {{ $attributes->merge(['class' => 'status status-'.$tone]) }}>
    @if($icon)<x-icon :name="$icon" />@endif
    <span>{{ $status ? $status->label() : $slot }}</span>
</span>
