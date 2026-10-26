{{-- Pagine di errore autonome: non dipendono dal database né da Vite, così funzionano anche durante un aggiornamento. --}}
<!DOCTYPE html>
<html lang="it" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c0f14">
    <title>@yield('title') · ManuTrack</title>
    <style>
        :root { color-scheme: dark; --bg:#0c0f14; --surface:#151a21; --line:rgba(255,255,255,.08); --fg:#e8ebf0; --muted:#8c95a3; --accent:#e8915a; --on:#1b0e06; }
        @media (prefers-color-scheme: light) { :root { color-scheme: light; --bg:#edeff2; --surface:#fff; --line:rgba(15,20,30,.08); --fg:#10141a; --muted:#5b6472; --accent:#a64b17; --on:#fff; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100dvh; display:grid; place-items:center; background:var(--bg); color:var(--fg);
               font: 16px/1.55 "IBM Plex Sans", "Segoe UI", Roboto, Arial, sans-serif;
               padding: max(1.5rem, env(safe-area-inset-top)) max(1rem, env(safe-area-inset-right)) max(1.5rem, env(safe-area-inset-bottom)) max(1rem, env(safe-area-inset-left));
               background-image: radial-gradient(700px 400px at 80% -10%, color-mix(in oklab, var(--accent) 18%, transparent), transparent 70%); background-repeat:no-repeat; }
        main { width:100%; max-width:30rem; background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:2rem; display:flex; flex-direction:column; gap:1rem; box-shadow:0 24px 60px -24px rgba(0,0,0,.5); }
        svg.mark { width:64px; height:auto; }
        .code { font-family: "IBM Plex Mono", ui-monospace, monospace; color:var(--accent); font-size:.85rem; letter-spacing:.12em; text-transform:uppercase; }
        h1 { margin:0; font-family:"Archivo Variable","Helvetica Neue",Arial,sans-serif; font-stretch:112%; font-size:1.75rem; line-height:1.15; }
        p { margin:0; color:var(--muted); }
        a.btn { align-self:flex-start; display:inline-flex; align-items:center; min-height:44px; padding:0 1.1rem; border-radius:12px; background:var(--accent); color:var(--on); font-weight:600; text-decoration:none; }
        .spin { width:18px; height:18px; border-radius:50%; border:2px solid var(--line); border-top-color:var(--accent); animation:s 1s linear infinite; display:inline-block; vertical-align:-3px; margin-right:.5rem; }
        @keyframes s { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .spin { animation:none; } }
    </style>
</head>
<body>
<main>
    <svg class="mark" viewBox="0 0 406 378" aria-hidden="true">
        <polygon points="0,0 203,202 203,339 90,227 86,377 0,292" fill="var(--accent)"/>
        <polygon points="406,0 203,202 203,339 316,227 320,377 406,292" fill="currentColor" opacity=".35"/>
    </svg>
    <span class="code">@yield('code')</span>
    <h1>@yield('heading')</h1>
    <p>@yield('message')</p>
    @yield('action')
</main>
</body>
</html>
