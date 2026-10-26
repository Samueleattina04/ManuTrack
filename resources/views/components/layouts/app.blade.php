@props(['title' => null, 'heading' => null])
@php
    $nav = \App\Support\Navigation::main();
    $admin = \App\Support\Navigation::admin();
    $mobile = \App\Support\Navigation::mobile();
    $user = auth()->user();
    $paletteItems = collect([...$nav, ...$admin])
        ->map(fn ($i) => ['label' => $i['label'], 'url' => $i['url'], 'keywords' => $i['keywords'], 'disabled' => $i['disabled']])
        ->push(['label' => __('profile.title'), 'url' => route('profile'), 'keywords' => 'profilo password tema', 'disabled' => false])
        ->values();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <x-head :title="$title" />
</head>
<body x-data="shortcuts" @keydown.window="onKey($event)" class="app-shell">
    <a href="#contenuto" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 btn btn-primary">{{ __('app.skip_to_content') }}</a>

    {{-- Barra laterale (PC): logo del cliente in alto, ManuTrack in piccolo in fondo --}}
    <aside class="app-sidebar hidden lg:flex flex-col gap-1 bg-surface border-r border-line-soft px-3 pb-4 z-40" aria-label="{{ __('app.nav.label') }}">
        <a href="{{ route('home') }}" class="flex min-h-[4.5rem] items-center px-2 pt-3 pb-3" aria-label="{{ __('app.nav.dashboard') }}">
            <x-company-logo />
        </a>
        <nav class="flex flex-col gap-0.5 overflow-y-auto">
            @foreach($nav as $item)
                <x-nav-link :item="$item" />
            @endforeach
            @if($admin !== [])
                <div class="mt-4 mb-1 px-3 font-mono text-[11px] uppercase tracking-wider text-muted">{{ __('app.nav.admin_group') }}</div>
                @foreach($admin as $item)
                    <x-nav-link :item="$item" />
                @endforeach
            @endif
        </nav>
        <div class="mt-auto flex flex-col gap-3 border-t border-line-soft px-1 pt-3">
            <a href="{{ route('profile') }}" class="flex items-center gap-3 rounded-[10px] p-2 hover:bg-surface-2" @if(request()->routeIs('profile')) aria-current="page" @endif>
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-surface-2 text-xs font-bold" aria-hidden="true">{{ $user->initials() }}</span>
                <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ $user->name }}</span><span class="block truncate text-xs text-muted">{{ $user->roleName() ? __('access.roles.'.$user->roleName()) : '' }}</span></span>
            </a>
            <div class="flex items-center justify-between gap-2 px-1">
                <x-theme-switcher />
                <button type="button" class="kbd" @click="helpOpen = true" title="{{ __('app.shortcuts.title') }}">?</button>
            </div>
            <x-powered-by :version="true" class="px-1" />
        </div>
    </aside>

    <div class="app-main">
        {{-- Barra superiore --}}
        <header class="app-topbar glass border-b border-line-soft">
            <div class="flex items-center gap-3" style="min-height: var(--mt-topbar-h)">
                <a href="{{ route('home') }}" class="lg:hidden shrink-0" aria-label="{{ __('app.nav.dashboard') }}">
                    <x-company-logo size="sm" />
                </a>
                <div class="min-w-0 flex-1">
                    @if($heading)
                        <h1 class="truncate text-right text-base font-bold lg:text-left lg:text-xl">{{ $heading }}</h1>
                    @endif
                </div>
                <button type="button" class="btn btn-secondary btn-sm hidden md:inline-flex min-w-[15rem] justify-start text-muted font-normal" @click="$dispatch('palette-open')">
                    <x-icon name="search" class="!w-4 !h-4" />
                    <span class="flex-1 text-left">{{ __('app.search_placeholder') }}</span>
                    <span class="kbd">Ctrl K</span>
                </button>
                <button type="button" class="btn btn-ghost btn-icon md:hidden" @click="$dispatch('palette-open')" aria-label="{{ __('app.search') }}">
                    <x-icon name="search" />
                </button>
                {{ $actions ?? '' }}
            </div>
        </header>

        <x-system-banner />

        <main id="contenuto" class="app-content pt-5 lg:pt-7 page-enter" tabindex="-1">
            @if(session('status') && ! request()->routeIs('settings.company'))
                <div class="mx-auto mb-5 max-w-6xl alert alert-ok" role="status"><x-icon name="check-circle" /><span>{{ session('status') }}</span></div>
            @endif
            @if(session('error'))
                <div class="mx-auto mb-5 max-w-6xl alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ session('error') }}</span></div>
            @endif
            {{ $slot }}
            <footer class="mx-auto mt-10 flex max-w-6xl justify-center lg:hidden"><x-powered-by :version="true" /></footer>
        </main>
    </div>

    {{-- Barra di navigazione in basso (telefono) --}}
    <nav class="bottom-nav glass border-t border-line-soft lg:hidden" aria-label="{{ __('app.nav.label') }}">
        <div class="grid grid-cols-5 items-end px-1 pt-1.5 pb-1.5" style="min-height: var(--mt-bottomnav-h)">
            @foreach($mobile as $item)
                @if($item['key'] === 'report')
                    <a href="{{ $item['url'] ?? '#' }}" aria-label="{{ $item['label'] }}" @if($item['disabled']) aria-disabled="true" @endif>
                        <span class="fab"><x-icon name="plus" /></span>
                        <span class="sr-only">{{ $item['label'] }}</span>
                    </a>
                @else
                    <a href="{{ $item['url'] ?? '#' }}" @if($item['active']) aria-current="page" @endif @if($item['disabled']) aria-disabled="true" class="opacity-50" @endif>
                        <x-icon :name="$item['icon']" class="!w-6 !h-6" />
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </nav>

    {{-- Palette comandi --}}
    <div x-data="commandPalette(@js($paletteItems))" @palette-open.window="show()" @keydown.escape.window="open = false">
        <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 bg-[var(--mt-overlay)] backdrop-blur-sm" @click="open = false"></div>
        <div x-cloak x-show="open" x-transition role="dialog" aria-modal="true" aria-label="{{ __('app.palette.title') }}"
             class="fixed left-1/2 z-50 w-[min(36rem,calc(100vw-2rem))] -translate-x-1/2 card overflow-hidden"
             style="top: calc(var(--safe-top) + 12vh)">
            <div class="flex items-center gap-2 border-b border-line px-3">
                <x-icon name="search" class="text-muted" />
                <input x-ref="input" x-model="query" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go()"
                       type="search" class="w-full bg-transparent py-3.5 outline-none" placeholder="{{ __('app.palette.placeholder') }}" aria-label="{{ __('app.palette.placeholder') }}">
                <span class="kbd">Esc</span>
            </div>
            <ul class="max-h-80 overflow-y-auto p-1.5" role="listbox">
                <template x-for="(item, i) in results" :key="item.label">
                    <li role="option" :aria-selected="i === active">
                        <a :href="item.url" class="nav-item" :class="i === active && 'bg-surface-2 text-fg'" @mouseenter="active = i" x-text="item.label"></a>
                    </li>
                </template>
                <li x-show="results.length === 0" class="px-3 py-6 text-center text-sm text-muted">{{ __('app.palette.empty') }}</li>
            </ul>
        </div>
    </div>

    {{-- Elenco scorciatoie --}}
    <div x-cloak x-show="helpOpen" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-[var(--mt-overlay)] p-4" @click.self="helpOpen = false" @keydown.escape.window="helpOpen = false">
        <div class="card w-full max-w-md p-5" role="dialog" aria-modal="true" aria-labelledby="shortcuts-title">
            <div class="mb-4 flex items-center justify-between">
                <h2 id="shortcuts-title" class="text-lg font-bold">{{ __('app.shortcuts.title') }}</h2>
                <button type="button" class="btn btn-ghost btn-icon" @click="helpOpen = false" aria-label="{{ __('app.close') }}"><x-icon name="x" /></button>
            </div>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2.5 text-sm">
                @foreach(__('app.shortcuts.list') as $keys => $what)
                    <dt class="flex gap-1">@foreach(explode(' ', $keys) as $k)<span class="kbd">{{ $k }}</span>@endforeach</dt>
                    <dd class="text-fg-2">{{ $what }}</dd>
                @endforeach
            </dl>
        </div>
    </div>

    <x-toasts />
    @livewireScriptConfig
</body>
</html>
