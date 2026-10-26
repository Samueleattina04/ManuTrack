@props(['size' => 'md'])
@php
    // Logo caricato dall'amministratore; in mancanza, la M di ManuTrack e il nome dell'azienda
    $logo = \App\Support\Brand\CompanyLogo::url();
    $logoDark = \App\Support\Brand\CompanyLogo::url(\App\Support\Brand\CompanyLogo::DARK);
    $company = (string) settings('company.name', config('app.name'));
    $h = ['sm' => 'h-7', 'md' => 'h-9', 'lg' => 'h-16'][$size] ?? 'h-9';
@endphp
@if($logo && $logoDark)
    {{-- Due versioni: si mostra quella adatta al tema --}}
    <span {{ $attributes->merge(['class' => 'inline-flex']) }}>
        <img src="{{ $logo }}" alt="{{ $company }}" class="company-logo-light {{ $h }} w-auto max-w-[13rem] object-contain object-left">
        <img src="{{ $logoDark }}" alt="{{ $company }}" class="company-logo-dark {{ $h }} w-auto max-w-[13rem] object-contain object-left">
    </span>
@elseif($logo)
    {{-- Una sola versione: nel tema scuro sta su una targhetta chiara, così resta leggibile --}}
    <span {{ $attributes->merge(['class' => 'company-logo-plate inline-flex']) }}>
        <img src="{{ $logo }}" alt="{{ $company }}" class="{{ $h }} w-auto max-w-[13rem] object-contain object-left">
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex min-w-0 items-center gap-2.5']) }}>
        <x-logo variant="mark" :shadow="false" class="{{ $h }} w-auto shrink-0" :label="null" />
        <span class="truncate font-display font-bold {{ $size === 'lg' ? 'text-2xl' : 'text-[15px]' }}">{{ $company }}</span>
    </span>
@endif
