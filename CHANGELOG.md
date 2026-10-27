# Registro delle modifiche

Tutte le modifiche rilevanti di ManuTrack sono elencate qui.
Formato ispirato a [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/), versioni secondo [SemVer](https://semver.org/lang/it/).

## [0.3.0] - Fase 3: app installabile e notifiche push di prova

### Aggiunto
- App installabile su PC, iPhone e Android:
  - service worker generato da Laravel, con versione legata al rilascio;
  - pagina "Sei offline";
  - schermate di avvio per iPhone in 11 formati.
- Avviso "Nuova versione disponibile": la pagina si ricarica solo quando si tocca "Aggiorna".
- Procedura guidata **App e notifiche** (`/app`) in tre passi (installa, notifiche, prova), con illustrazioni animate per iPhone e Android e istruzioni per riattivare le notifiche negate.
- Il permesso alle notifiche si chiede solo dopo un tocco, mai all'apertura della pagina.
- Notifiche push:
  - iscrizione per dispositivo;
  - "Invia notifica di prova";
  - numero sul pallino dell'icona dove supportato;
  - rinnovo automatico dell'iscrizione;
  - eliminazione delle iscrizioni rifiutate o fallite 5 volte di seguito.
- Pagina **Diagnostica notifiche** (`/notifiche/diagnostica`): controlli su HTTPS, service worker, installazione, versione di iOS, permesso, iscrizione e chiavi; prova su uno o tutti i propri dispositivi; "Copia rapporto"; elenco dei dispositivi.
- Invito all'installazione nella panoramica, su telefono.
- Guida [`docs/TEST-DISPOSITIVI.md`](docs/TEST-DISPOSITIVI.md):
  - sottodominio con HTTPS su DirectAdmin;
  - chiavi VAPID anche senza SSH;
  - checklist per iPhone e Android reali.
- Controllo automatico nel browser `scripts/e2e/pwa-check.cjs`.

### Corretto
- Le notifiche push fallivano sui server senza le estensioni `bcmath` o `gmp`: un avviso della libreria veniva trattato come errore. Ora funzionano anche senza (le estensioni restano consigliate per la velocità).

## [0.2.0] - Fase 2: accesso, ruoli e impostazioni dell'azienda

### Aggiunto
- Accesso con Laravel Fortify: login con limite di tentativi, password dimenticata, verifica in due passaggi (app di autenticazione e codici di recupero), conferma della password per le aree sensibili. Niente registrazione libera.
- Sei ruoli dell'azienda (amministratore, responsabile manutenzione, tecnico, magazziniere, richiedente, osservatore) con permessi per modulo e una matrice ruoli × permessi modificabile dall'amministratore. A ogni aggiornamento ruoli e permessi vengono riallineati senza perdere le modifiche fatte.
- Persone: elenco con ricerca e filtri, inviti via email con link valido 7 giorni (nel database c'è solo l'impronta del codice), cambio ruolo, disattivazione e riattivazione, azzeramento della verifica in due passaggi, invio del link per una nuova password. Resta sempre almeno un amministratore attivo.
- Impostazioni dell'azienda: nome, fuso orario, lingua, logo del cliente (versione principale e versione facoltativa per il tema scuro), palette tra le quattro preimpostazioni o personalizzata con contrasti verificati, proposta dei colori presi dal logo, icone dell'app rigenerate per la palette personalizzata.
- Palette **Pistacchio** (quarta preimpostazione, valori provvisori).
- Intestazione con il logo del cliente; ManuTrack resta in piccolo nella pagina di accesso e nel piè di pagina.
- Profilo personale: dati, password, verifica in due passaggi, tema.
- Registro delle attività (accessi, persone, ruoli, impostazioni, sistema) con ricerca; non salva mai password o segreti.
- Pagina Sistema riservata agli amministratori, con conferma della password e pulsanti "Applica ora", "Fai ritentare al cron" e "Crea una copia ora". L'avviso sugli aggiornamenti compare solo a chi gestisce il sistema.
- Lingua e fuso orario dell'azienda applicati a ogni pagina.

### Modificato
- Lo stato "operativa" passa a un verde acqua, per distinguerlo dagli accenti verdi.
- Nuovi token `--mt-accent-fg` (testi d'accento) e `--mt-accent-soft` (aloni ed evidenziazioni).
- Tutte le pagine dell'app richiedono l'accesso.

### Corretto
- La palette scelta non veniva applicata quando era diversa da Grafite e Rame: lo stile con i colori dell'azienda veniva caricato prima del CSS principale.
- Prima dell'installazione le pagine portano all'installazione anziché alla pagina di accesso.

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
