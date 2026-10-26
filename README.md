<p align="center"><img src="public/icons/rame/icon-192.png" width="96" alt="ManuTrack"></p>

# ManuTrack

Software per la gestione della manutenzione di macchine e impianti (CMMS): ordini di lavoro, manutenzione preventiva, magazzino ricambi e indicatori, con un'app per PC, iPhone e Android.

**Un'installazione per cliente:** ogni azienda ha la sua cartella con una copia dell'app e un suo database MySQL. Gira su un normale hosting condiviso con **PHP 8.3 e MySQL**, anche senza SSH (pannello DirectAdmin).

> Stato: **fase 2 di 11** (versione 0.2.0): accesso con verifica in due passaggi, ruoli e permessi, inviti, impostazioni dell'azienda con logo e colori, registro attività. Il piano completo è in [`docs/PROPOSTA.md`](docs/PROPOSTA.md).

## Tecnologie

Laravel 12 · Fortify · spatie/laravel-permission · Livewire 3 · Alpine.js · Tailwind CSS 4 · Vite · MySQL 8 / MariaDB · Pest · Larastan · Pint. Three.js arriverà nelle pagine 3D, caricato solo dove serve.

## Avvio rapido (sviluppo)

```bash
composer install
npm install
cp .env.example .env    # imposta APP_ENV=local, APP_DEBUG=true, i dati del database e SESSION_SECURE_COOKIE=false
php artisan key:generate
npm run build
php artisan migrate --seed    # amministratore di prova: admin@example.com / manutrack-demo
php artisan serve
```

In alternativa `php artisan manutrack:install` installa come in produzione.

## Comandi principali

| Comando | Scopo |
|---|---|
| `php artisan manutrack:install` | installa (database, azienda, amministratore, chiavi push) |
| `php artisan manutrack:update` | applica un aggiornamento (copia del database, migrazioni, cache) |
| `php artisan manutrack:backup` / `manutrack:restore` | copie del database in PHP e ripristino |
| `php artisan manutrack:vapid` | genera le chiavi per le notifiche push |
| `php artisan manutrack:icons` | rigenera favicon e icone dell'app dal logo |
| `scripts/release.sh` / `scripts\release.ps1` | prepara il pacchetto da caricare via FTP |
| `php artisan test` | test automatici (database `manutrack_test`) |
| `vendor/bin/pint` · `vendor/bin/phpstan analyse` | stile e analisi statica |

## Documentazione

- [Installazione e aggiornamento su DirectAdmin](docs/INSTALLAZIONE.md) (HTTPS, cron, chiavi push, aggiornamenti)
- [Specifica funzionale](docs/SPECIFICA.md), aggiornata a ogni fase
- [Design system](docs/DESIGN.md) e [anteprima dello stile](docs/anteprima/stile.html)
- [Librerie e licenze](docs/LIBRERIE.md)
- [Registro delle modifiche](CHANGELOG.md)

## Struttura

```
app/Actions/Setup        installazione e chiavi push
app/Support/Update       aggiornamenti (manuali e dal cron), release.json
app/Support/Backup       copie del database in PHP
app/Support/Brand        geometria del logo, palette, icone generate con GD
resources/views          layout PC/telefono, componenti Blade, pagine
resources/css/app.css    token e componenti del design system
lang/it                  testi dell'interfaccia
scripts/                 pacchetto di rilascio, logo, screenshot di controllo
```
