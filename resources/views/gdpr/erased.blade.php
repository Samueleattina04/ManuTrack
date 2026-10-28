{{-- Installazione cancellata: pagina a sé, senza database, sessioni né impostazioni --}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Installazione cancellata · ManuTrack</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; background: Canvas; color: CanvasText; }
        main { max-width: 760px; margin: 0 auto; padding: 32px 16px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin: 8px 0 16px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid color-mix(in srgb, CanvasText 18%, transparent); }
        td.n { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { opacity: .7; font-size: 13px; }
        details { margin: 12px 0; }
    </style>
</head>
<body>
<main>
    <h1>Installazione di ManuTrack cancellata</h1>
    <p>Tutti i dati dell'azienda sono stati cancellati il {{ isset($report['cancellata_il']) ? \Illuminate\Support\Carbon::parse($report['cancellata_il'])->translatedFormat('j F Y \a\l\l\e H:i') : '—' }} ({{ $report['richiesta_da'] ?? '—' }}, {{ $report['modo'] ?? '' }}).</p>
    <p><strong>{{ number_format((int) ($report['righe_cancellate'] ?? 0), 0, ',', '.') }}</strong> righe in {{ count($report['tabelle_cancellate'] ?? []) }} tabelle del database.</p>

    <h2>Cartelle svuotate</h2>
    <table>
        <thead><tr><th>Cartella</th><th class="n">File</th><th class="n">Dimensione</th></tr></thead>
        <tbody>
        @foreach(($report['cartelle_svuotate'] ?? []) as $label => $f)
            <tr><td>{{ $label }}</td><td class="n">{{ $f['files'] }}</td><td class="n">{{ \Illuminate\Support\Number::fileSize((int) $f['bytes'], 1) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <h2>Cosa resta fuori da ManuTrack</h2>
    <ul>@foreach(($report['non_cancellato'] ?? []) as $line)<li>{{ $line }}</li>@endforeach</ul>

    <details>
        <summary>Righe cancellate per tabella</summary>
        <table>
            <tbody>
            @foreach(($report['tabelle_cancellate'] ?? []) as $table => $rows)
                <tr><td><code>{{ $table }}</code></td><td class="n">{{ $rows }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </details>
    <p class="muted">Il rapporto è nel file storage/app/manutrack-cancellata.json dell'installazione e non contiene dati personali. Versione {{ $report['versione'] ?? '' }}.</p>
</main>
</body>
</html>
