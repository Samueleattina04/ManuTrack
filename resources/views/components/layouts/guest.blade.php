@props(['title' => null, 'wide' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <x-head :title="$title" />
</head>
<body class="min-h-dvh" style="background-image: radial-gradient(900px 520px at 85% -10%, var(--mt-glow), transparent 70%); background-repeat: no-repeat">
    <div class="mx-auto flex min-h-dvh w-full flex-col gap-8 py-8 {{ $wide ? 'max-w-5xl' : 'max-w-xl' }}"
         style="padding-left: max(1rem, var(--safe-left)); padding-right: max(1rem, var(--safe-right)); padding-top: calc(2rem + var(--safe-top)); padding-bottom: calc(2rem + var(--safe-bottom))">
        <header class="flex items-center justify-between gap-4">
            <x-logo variant="horizontal" class="h-8 w-auto" />
            <x-theme-switcher />
        </header>
        <main id="contenuto" class="flex-1 page-enter">
            {{ $slot }}
        </main>
        <footer class="text-xs text-muted">ManuTrack <span class="font-mono">{{ manutrack_version() }}</span></footer>
    </div>
    @livewireScriptConfig
</body>
</html>
