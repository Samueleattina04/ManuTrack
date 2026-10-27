@props(['title' => null, 'wide' => false, 'brand' => true])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <x-head :title="$title" />
</head>
<body class="min-h-dvh" style="background-image: radial-gradient(900px 520px at 50% -12%, var(--mt-glow), transparent 70%); background-repeat: no-repeat">
    <div class="mx-auto flex min-h-dvh w-full flex-col gap-8 {{ $wide ? 'max-w-5xl' : 'max-w-md' }}"
         style="padding-left: max(1rem, var(--safe-left)); padding-right: max(1rem, var(--safe-right)); padding-top: calc(1.5rem + var(--safe-top)); padding-bottom: calc(1.25rem + var(--safe-bottom))">
        <header class="flex items-center justify-end">
            <x-theme-switcher />
        </header>
        @if($brand)
            <div class="flex justify-center pt-2"><x-company-logo size="lg" /></div>
        @endif
        <main id="contenuto" class="flex-1 page-enter">
            {{ $slot }}
        </main>
        <footer class="flex justify-center"><x-powered-by :version="true" /></footer>
    </div>
    <x-update-banner />
    @livewireScriptConfig
</body>
</html>
