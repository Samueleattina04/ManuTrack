# Registro delle modifiche

Tutte le modifiche rilevanti di ManuTrack sono elencate qui.
Formato ispirato a [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/), versioni secondo [SemVer](https://semver.org/lang/it/).

## [0.5.1] - Correzioni alla fase 5

### Aggiunto
- **Correggi stato** di un ordine di lavoro, solo per l'amministratore:
  - qualunque stato, anche dopo la chiusura;
  - motivo obbligatorio;
  - registrazione nello storico dell'ordine (segnata "correzione") e nel registro attività;
  - aggiustamento dei fermi macchina collegati (chiusura, riapertura, spostamento con la data di completamento).
  - Gli altri ruoli restano vincolati al ciclo normale.
- Avviso nella pagina Sistema se in produzione esistono account con indirizzi `@example.com`, `.org` o `.net`.
- Test che verificano che `manutrack:install` e l'installazione dal browser non creano account né dati dimostrativi.

### Modificato
- I seeder dimostrativi non creano nulla in produzione, salvo con `MANUTRACK_DEMO=true`.

## [0.5.0] - Fase 5: segnalazioni e ordini di lavoro

### Aggiunto
- **Segnalazione rapida dal telefono** (`/segnala`, pulsante centrale): pochi tocchi.
  - Macchina da QR inquadrato dal vivo, da foto del QR, da ricerca, dalle recenti, oppure "non è una macchina".
  - Cosa succede (con frasi rapide), macchina ferma sì/no, urgenza, foto.
- **Lettore QR nell'app:** `BarcodeDetector` su Android, `jsQR` su iPhone, caricato solo quando serve.
- **Invio differito senza rete:**
  - pagina disponibile offline;
  - segnalazione salvata sul telefono (IndexedDB) e inviata da sola al ritorno della rete (Background Sync su Android, alla riapertura su iPhone);
  - senza doppioni e con l'ora di scrittura;
  - mai cancellata da sola;
  - avviso "in attesa di invio" in tutte le pagine.
- **Notifica urgente immediata** per le segnalazioni critiche e per le macchine di criticità A ferme; le altre in coda.
- **Valutazione delle segnalazioni:** accetta (crea l'ordine di lavoro con assegnatari, scadenza, checklist), respingi con motivo, duplicata; esito notificato a chi ha segnalato.
- **Ordini di lavoro:**
  - numerazione `OdL-AAAA-NNNNN`;
  - tipi e priorità;
  - ciclo di vita completo come da proposta (aperto, pianificato, in corso, in attesa con motivo, completato, chiuso, riaperto, annullato), controllato anche per l'amministratore;
  - storico degli stati.
- "Prendo io", assegnatari con notifica (urgente se critico), checklist da modelli con voci a spunta o a valore, cronometro (uno per persona) e tempi a mano.
- Fermi macchina aperti dalla segnalazione e chiusi al completamento, con macchina di nuovo operativa.
- Codici guasto (problema, causa, rimedio), note di chiusura, foto, commenti, costi (manodopera × costo orario + esterni).
- Elenchi con schede per fase, "Solo i miei", filtri, ordine per urgenza, scaduti; scorciatoie `N`, `F`, `M`, `G E`.
- Impostazioni manutenzione: modelli di checklist, codici guasto, costo orario.
- Panoramica con segnalazioni da valutare e "I miei ordini"; scheda macchina con storico interventi e "Segnala un guasto".
- **Foto in WebP in tutta l'app** (richiesta durante la fase):
  - sul telefono si riducono a 1600 px in WebP, o in JPEG su Safari;
  - sul server WebP qualità 80, 1600 px più miniatura 400 px, senza EXIF né GPS, raddrizzate anche senza l'estensione `exif`;
  - ripiego JPEG segnalato in installazione e nella pagina Sistema;
  - le foto HEIC non convertibili si conservano tra i documenti con un avviso.
- Comando `php artisan manutrack:foto-ottimizza` (con `--prova`) per convertire le foto già salvate, verificandole prima di cancellare gli originali.
- All'uscita si svuotano le pagine salvate sul telefono (`Clear-Site-Data`).
- Test della matrice dei permessi per ogni ruolo; controlli nel browser `scripts/e2e/report-check.cjs`.
- Persone, segnalazioni e ordini dimostrativi inventati.
- Libreria `jsqr` (Apache-2.0).

### Modificato
- Il richiedente trova "Segnalazioni" al posto di "Ordini" nella barra in basso.
- Dal QR stampato, chi può solo segnalare arriva direttamente alla segnalazione di quella macchina.

### Corretto
- Un nome di variabile duplicato avrebbe nascosto lo storico delle letture dei contatori nella scheda macchina (trovato dall'analisi statica prima del rilascio).

## [0.4.0] - Fase 4: macchine e impianti

### Aggiunto
- **Notifiche push urgenti:** il guasto o il fermo di una macchina di criticità A (che ferma la linea) avvisa subito i responsabili.
  - L'invio parte appena l'app ha risposto, senza aspettare il cron, ad alta priorità.
  - Se fallisce per un problema temporaneo, si ritenta dalla coda.
  - Le altre notifiche restano in coda.
  - La pagina Sistema mostra come il server gestisce l'invio immediato.
- **Ubicazioni** a tre livelli (sito › area › linea), con spostamenti che aggiornano tutto il ramo.
- **Categorie** con icona e forma per la futura vista 3D.
- **Macchine:**
  - elenco con ricerca, filtri (anche per ramo dell'ubicazione), conteggi per stato e quattro ordinamenti;
  - scheda con stato, criticità A/B/C, sottogruppi, dati tecnici, garanzia e costo (visibile solo a chi vede i costi);
  - modulo di creazione e modifica;
  - eliminazione protetta.
- **Cambio di stato** dalla scheda, con nota, registro attività e notifiche ai responsabili.
- **Contatori** con letture (numeri all'italiana), controllo dei valori progressivi, sostituzione del contatore e storico.
- **Foto e documenti** su disco privato:
  - si scaricano solo con il permesso;
  - le foto sono raddrizzate e ridimensionate (1600 px più miniatura 400 px), senza metadati né posizione GPS.
- **Codici QR** per macchina (codice casuale rigenerabile) ed **etichette A4** in tre formati, con "salta le prime" per i fogli già usati.
- **Importazione** da Excel (XLSX) o CSV:
  - anteprima con errori per riga;
  - creazione di ubicazioni e categorie mancanti;
  - aggiornamento per codice senza cancellare i dati con le celle vuote;
  - tutto o niente.
- Permessi nuovi: cambiare lo stato, registrare letture, aggiungere foto e documenti (predefiniti per responsabile e tecnico).
- Panoramica: elenco delle macchine ferme o in guasto.
- Macchine dimostrative inventate per lo sviluppo.
- Libreria `openspout/openspout` (MIT) per leggere i file Excel.

### Corretto
- La guida `TEST-DISPOSITIVI.md` consigliava `config:cache` dopo aver generato le chiavi VAPID: ora indica `config:clear`. Con `config:cache` le modifiche successive a `.env` sarebbero state ignorate.

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
