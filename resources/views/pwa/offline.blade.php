{{-- Pagina mostrata dal service worker quando manca la connessione: autonoma, con lo stile incluso. --}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c0f14">
    <title>{{ __('pwa.offline.title') }} · ManuTrack</title>
    <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        :root { color-scheme: dark; --bg:#0c0f14; --surface:#151a21; --line:rgba(255,255,255,.08); --fg:#e8ebf0; --muted:#8c95a3; --accent:#e8915a; --on:#1b0e06; }
        @media (prefers-color-scheme: light) { :root { color-scheme: light; --bg:#edeff2; --surface:#fff; --line:rgba(15,20,30,.08); --fg:#10141a; --muted:#5b6472; --accent:#a64b17; --on:#fff; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100dvh; display:grid; place-items:center; background:var(--bg); color:var(--fg);
               font: 16px/1.55 "IBM Plex Sans", "Segoe UI", Roboto, Arial, sans-serif;
               padding: max(1.5rem, env(safe-area-inset-top)) max(1rem, env(safe-area-inset-right)) max(1.5rem, env(safe-area-inset-bottom)) max(1rem, env(safe-area-inset-left)); }
        main { width:100%; max-width:26rem; display:flex; flex-direction:column; align-items:center; gap:1rem; text-align:center; }
        .ring { width:88px; height:88px; border-radius:50%; display:grid; place-items:center; background:var(--surface); border:1px solid var(--line); }
        .ring svg { width:40px; height:40px; stroke:var(--muted); fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
        h1 { margin:0; font-family:"Archivo Variable","Helvetica Neue",Arial,sans-serif; font-stretch:112%; font-size:1.6rem; }
        p { margin:0; color:var(--muted); }
        button { margin-top:.5rem; min-height:52px; padding:0 1.4rem; border:0; border-radius:14px; background:var(--accent); color:var(--on); font:inherit; font-weight:600; cursor:pointer; }
        .brand { margin-top:1.5rem; font-size:.8rem; color:var(--muted); }
    </style>
</head>
<body>
<main>
    <div class="ring" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 20h.01"/><path d="M8.5 16.429a5 5 0 0 1 7 0"/><path d="M5 12.859a10 10 0 0 1 5.17-2.69"/><path d="M19 12.859a10 10 0 0 0-2.007-1.523"/><path d="M2 8.82a15 15 0 0 1 4.177-2.643"/><path d="M22 8.82a15 15 0 0 0-11.288-3.764"/><path d="m2 2 20 20"/></svg></div>
    <h1>{{ __('pwa.offline.title') }}</h1>
    <p>{{ __('pwa.offline.text') }}</p>
    <button type="button" data-reload>{{ __('pwa.offline.retry') }}</button>
    <p class="brand">ManuTrack</p>
</main>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    window.addEventListener('online', function () { location.reload(); });
    document.querySelector('[data-reload]').addEventListener('click', function () { location.reload(); });
</script>
</body>
</html>
