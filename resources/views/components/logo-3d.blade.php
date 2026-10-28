{{-- La M di ManuTrack: SVG subito (leggero, sempre visibile), sostituito dalla versione 3D
     quando il dispositivo la regge e la persona non ha chiesto meno movimento. --}}
<div x-data="logo3d()" @click="askMotion()" {{ $attributes->merge(['class' => 'relative']) }}>
    <x-logo variant="mark" class="h-full w-full transition-opacity duration-300" :label="null" x-bind:class="ready ? 'opacity-0' : ''" />
    <div x-ref="stage" class="pointer-events-none absolute inset-[-12%]" aria-hidden="true"></div>
</div>
