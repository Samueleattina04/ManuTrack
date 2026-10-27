@props(['title' => null])
@php
    $company = (string) settings('company.name', config('app.name'));
    $pageTitle = $title ? $title.' · ManuTrack' : 'ManuTrack · '.$company;
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ __('app.meta_description') }}">

{{-- Tema: applicato prima del disegno per evitare il lampo di colore --}}
<script>
    (function () {
        var mode = 'dark';
        try { mode = localStorage.getItem('mt-theme') || 'dark'; } catch (e) {}
        var theme = mode === 'system' ? (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark') : (mode === 'light' ? 'light' : 'dark');
        document.documentElement.dataset.theme = theme;
        document.documentElement.dataset.themeMode = mode;
    })();
</script>

{{-- PWA e iOS --}}
<link rel="manifest" href="{{ route('manifest') }}">
<meta name="theme-color" content="#0c0f14">
<meta name="application-name" content="ManuTrack">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="ManuTrack">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="format-detection" content="telephone=no">
<link rel="icon" href="{{ \App\Support\Brand\Brand::iconUrl('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ \App\Support\Brand\Brand::iconUrl('favicon-32.png') }}" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="{{ \App\Support\Brand\Brand::iconUrl('apple-touch-icon.png') }}">
@foreach(\App\Support\Brand\SplashRenderer::DEVICES as [$sw, $sh, $cw, $ch, $ratio])
<link rel="apple-touch-startup-image" media="{{ \App\Support\Brand\SplashRenderer::media($cw, $ch, $ratio) }}" href="{{ \App\Support\Brand\Brand::splashUrl($sw, $sh) }}">
@endforeach
@auth
<meta name="vapid-public-key" content="{{ config('manutrack.webpush.public_key') }}">
@endauth

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Dopo il CSS principale: la palette dell'azienda sostituisce quella predefinita --}}
<style>{!! \App\Support\Brand\Brand::cssVariables() !!}</style>
@livewireStyles
{{ $slot }}
