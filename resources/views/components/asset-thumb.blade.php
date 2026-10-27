@props(['asset', 'size' => 'h-11 w-11'])
{{-- Miniatura della macchina: la foto principale oppure l'icona della categoria --}}
@if($asset->cover)
    <img src="{{ $asset->cover->url('thumb') }}" alt="" loading="lazy" decoding="async" {{ $attributes->merge(['class' => $size.' shrink-0 rounded-[10px] object-cover bg-surface-2']) }}>
@else
    <span {{ $attributes->merge(['class' => $size.' grid shrink-0 place-items-center rounded-[10px] bg-surface-2 text-muted']) }} aria-hidden="true">
        <x-icon :name="$asset->category?->icon ?? 'factory'" />
    </span>
@endif
