# Registro delle modifiche

Tutte le modifiche rilevanti di ManuTrack sono elencate qui.
Formato ispirato a [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/), versioni secondo [SemVer](https://semver.org/lang/it/).

## [0.1.0] - Fase 1: fondamenta

### Aggiunto
- Base Laravel 12 con Livewire 3, Alpine.js, Tailwind CSS 4 e Vite.
- Design system "Grafite e Rame" con tema scuro e chiaro, preimpostazioni "Acciaio e Ciano" e "Ardesia e Indaco", componenti Blade riutilizzabili e documentazione in `docs/DESIGN.md`.
- Logo vettoriale ricavato dall'originale, con colori governati da variabili CSS e varianti (completo, solo M, orizzontale, monocromatico chiaro e scuro).
- Icone dell'app, favicon e apple-touch-icon generate dal logo (`php artisan manutrack:icons`).
- Struttura dell'interfaccia per PC (barra laterale) e telefono (barra in basso, aree sicure, unità dvh).
- Installazione da riga di comando (`manutrack:install`) e dal browser (`/install`, protetta da codice, utilizzabile una sola volta).
- Verifica dei requisiti del server, comprese GD o Imagick.
- Aggiornamento ripetibile (`manutrack:update`), anche automatico dal cron solo su pacchetti completi, con blocco contro le esecuzioni doppie.
- Copie del database in PHP prima di ogni aggiornamento (`manutrack:backup`, `manutrack:restore`), con conservazione delle ultime copie.
- Generazione delle chiavi push (`manutrack:vapid`).
- Script per preparare il pacchetto di rilascio su Linux, macOS e Windows.
- Testi dell'interfaccia in italiano in `lang/it`.
