@php
    $palette = \App\Support\Brand\Brand::paletteKey();
    $tokens = [
        'bg' => 'Fondo', 'bg-2' => 'Fondo 2', 'surface' => 'Superficie', 'surface-2' => 'Superficie rialzata',
        'line' => 'Bordo', 'fg' => 'Testo', 'fg-2' => 'Testo 2', 'muted' => 'Testo secondario',
        'accent' => 'Accento', 'accent-2' => 'Accento chiaro',
    ];
    $states = ['ok' => 'Operativa', 'ko' => 'Guasto / critica', 'mnt' => 'Manutenzione', 'stop' => 'Ferma', 'warn' => 'Scadenza / attesa'];
@endphp
<x-layouts.app title="Design system" heading="Design system">
    <div class="mx-auto flex max-w-6xl flex-col gap-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="max-w-prose text-fg-2">Riferimento dal vivo dei token e dei componenti di ManuTrack, documentati in <span class="font-mono">docs/DESIGN.md</span>. Cambia tema per vedere le due varianti.</p>
            <x-theme-switcher />
        </div>

        {{-- Logo --}}
        <section class="flex flex-col gap-4" aria-labelledby="d-logo">
            <div><span class="eyebrow">Marchio</span><h2 id="d-logo" class="text-xl font-bold">Logo</h2></div>
            <div class="grid gap-3 sm:grid-cols-3">
                <figure class="card overflow-hidden">
                    <img src="{{ asset('images/logo-originale.png') }}" alt="Logo ManuTrack originale" class="aspect-square w-full object-cover" width="600" height="600" loading="lazy">
                    <figcaption class="p-3 text-sm"><b>Originale</b> <span class="text-muted">non modificato</span></figcaption>
                </figure>
                <figure class="card overflow-hidden">
                    <div class="logo-on-dark grid aspect-square place-items-center bg-[#0e1218]"><x-logo class="w-3/4" /></div>
                    <figcaption class="p-3 text-sm"><b>Su fondo scuro</b></figcaption>
                </figure>
                <figure class="card overflow-hidden">
                    <div class="logo-on-light grid aspect-square place-items-center bg-[#f4f5f7]"><x-logo class="w-3/4" /></div>
                    <figcaption class="p-3 text-sm"><b>Su fondo chiaro</b></figcaption>
                </figure>
            </div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="card grid aspect-[4/3] place-items-center p-4"><x-logo variant="mark" class="w-1/2" /></div>
                <div class="card grid aspect-[4/3] place-items-center p-4"><x-logo variant="horizontal" class="w-11/12" /></div>
                <div class="card logo-mono-light grid aspect-[4/3] place-items-center bg-[#0e1218] p-4"><x-logo class="w-2/3" /></div>
                <div class="card logo-mono-dark grid aspect-[4/3] place-items-center bg-[#f4f5f7] p-4"><x-logo class="w-2/3" /></div>
            </div>
            <div class="card flex flex-wrap items-end gap-6 p-5">
                @foreach(['icon-512.png' => ['Icona 512', 'rounded-[28px] w-28'], 'maskable-512.png' => ['Maskable', 'rounded-full w-24'], 'apple-touch-icon.png' => ['apple-touch-icon 180', 'rounded-[18px] w-20'], 'favicon-32.png' => ['favicon 32', 'w-8']] as $file => [$label, $cls])
                    <figure class="flex flex-col items-center gap-2 text-xs text-muted">
                        <img src="{{ asset('icons/'.$palette.'/'.$file) }}" alt="{{ $label }}" class="{{ $cls }}" loading="lazy">
                        <figcaption>{{ $label }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </section>

        {{-- Colori --}}
        <section class="flex flex-col gap-4" aria-labelledby="d-color">
            <div><span class="eyebrow">Colore</span><h2 id="d-color" class="text-xl font-bold">Token</h2></div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach($tokens as $token => $label)
                    <div class="card overflow-hidden">
                        <div class="h-14 border-b border-line-soft" style="background: var(--mt-{{ $token }})"></div>
                        <div class="p-2.5 text-sm"><b>{{ $label }}</b><div class="font-mono text-xs text-muted">--mt-{{ $token }}</div></div>
                    </div>
                @endforeach
            </div>
            <h3 class="mt-2 text-sm font-semibold uppercase tracking-wider text-muted">Stati (sempre con icona e testo)</h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                @foreach($states as $token => $label)
                    <div class="card overflow-hidden">
                        <div class="h-14" style="background: var(--mt-{{ $token }})"></div>
                        <div class="p-2.5 text-sm"><b>{{ $label }}</b><div class="font-mono text-xs text-muted">--mt-{{ $token }}</div></div>
                    </div>
                @endforeach
            </div>
            <h3 class="mt-2 text-sm font-semibold uppercase tracking-wider text-muted">Preimpostazioni</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($palettes as $key => $p)
                    <span class="status {{ $key === $palette ? 'status-accent' : 'status-stop' }}"><span class="h-3 w-3 rounded-full" style="background: {{ $p['accent_d'] }}"></span>{{ $p['label'] }}</span>
                @endforeach
            </div>
        </section>

        {{-- Tipografia --}}
        <section class="flex flex-col gap-4" aria-labelledby="d-type">
            <div><span class="eyebrow">Tipografia</span><h2 id="d-type" class="text-xl font-bold">Caratteri</h2></div>
            <div class="grid gap-3 md:grid-cols-3">
                <div class="card p-5"><div class="eyebrow mb-2">Titoli · Archivo</div><div class="font-display text-4xl font-bold" style="font-stretch:115%">Fermo linea 2</div></div>
                <div class="card p-5"><div class="eyebrow mb-2">Testo · IBM Plex Sans</div><div class="text-2xl font-medium">Sostituire la guarnizione del cilindro</div></div>
                <div class="card p-5"><div class="eyebrow mb-2">Codici · IBM Plex Mono</div><div class="font-mono text-2xl">OdL-2026-00418</div></div>
            </div>
        </section>

        {{-- Componenti --}}
        <section class="flex flex-col gap-4" aria-labelledby="d-comp">
            <div><span class="eyebrow">Componenti</span><h2 id="d-comp" class="text-xl font-bold">Elementi base</h2></div>
            <div class="grid gap-3 lg:grid-cols-2">
                <div class="card flex flex-col gap-4 p-5">
                    <h3 class="font-semibold">Pulsanti</h3>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary"><x-icon name="plus" />Nuovo ordine</button>
                        <button type="button" class="btn btn-secondary">Annulla</button>
                        <button type="button" class="btn btn-ghost">Secondario</button>
                        <button type="button" class="btn btn-danger"><x-icon name="alert-triangle" />Segnala guasto</button>
                        <button type="button" class="btn btn-primary" disabled>Disattivato</button>
                    </div>
                    <button type="button" class="btn btn-primary btn-xl"><x-icon name="camera" />Azione da campo (56 px)</button>
                    <h3 class="font-semibold">Campi</h3>
                    <div class="field">
                        <label class="field-label" for="d-input">Macchina</label>
                        <input id="d-input" class="input" value="PR-02 · Pressa idraulica 250 t">
                        <span class="field-hint">Testo a 16 px: niente zoom involontario su iPhone.</span>
                    </div>
                    <div class="field">
                        <label class="field-label" for="d-input-err">Descrizione</label>
                        <input id="d-input-err" class="input" aria-invalid="true" aria-describedby="d-err">
                        <span id="d-err" class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />Il campo descrizione è obbligatorio.</span>
                    </div>
                </div>
                <div class="card flex flex-col gap-4 p-5">
                    <h3 class="font-semibold">Stati macchina</h3>
                    <div class="flex flex-wrap gap-2">@foreach($statuses as $s)<x-status :status="$s" />@endforeach</div>
                    <h3 class="font-semibold">Priorità</h3>
                    <div class="flex flex-wrap gap-4">@foreach($priorities as $p)<x-priority :priority="$p" />@endforeach</div>
                    <h3 class="font-semibold">Avvisi</h3>
                    <div class="alert alert-ok"><x-icon name="check-circle" /><span>Ordine chiuso e costi registrati.</span></div>
                    <div class="alert alert-warn"><x-icon name="clock" /><span>Piano preventivo in scadenza tra 2 giorni.</span></div>
                    <div class="alert alert-ko"><x-icon name="alert-triangle" /><span>Segnalazione critica sulla linea 1.</span></div>
                    <h3 class="font-semibold">Scorciatoie</h3>
                    <div class="flex flex-wrap items-center gap-2 text-sm text-fg-2"><span class="kbd">Ctrl K</span> palette <span class="kbd">G</span><span class="kbd">O</span> ordini <span class="kbd">?</span> aiuto</div>
                </div>
            </div>
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table min-w-[640px]">
                        <thead><tr><th>N.</th><th>Titolo</th><th>Macchina</th><th>Stato</th><th>Priorità</th><th>Scadenza</th></tr></thead>
                        <tbody>
                            <tr><td class="code">OdL-2026-00418</td><td>Perdita olio cilindro principale</td><td class="code">PR-02</td><td><x-status :status="\App\Enums\AssetStatus::Fault" /></td><td><x-priority :priority="\App\Enums\Priority::Critical" /></td><td class="num font-semibold text-ko">ieri</td></tr>
                            <tr><td class="code">OdL-2026-00415</td><td>Ingrassaggio guide asse Z</td><td class="code">TN-05</td><td><x-status :status="\App\Enums\AssetStatus::Maintenance" /></td><td><x-priority :priority="\App\Enums\Priority::Medium" /></td><td class="num">28/10</td></tr>
                            <tr><td class="code">OdL-2026-00409</td><td>Sostituzione filtri aspirazione</td><td class="code">CP-01</td><td><x-status :status="\App\Enums\AssetStatus::Operational" /></td><td><x-priority :priority="\App\Enums\Priority::Low" /></td><td class="num">04/11</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="text-xs text-muted">Dati di esempio inventati.</p>
        </section>
    </div>
</x-layouts.app>
