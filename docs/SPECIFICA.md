# Specifica funzionale di ManuTrack

Documento scritto man mano, fase per fase. Descrive cosa fa l'app dal punto di vista di chi la usa e di chi la gestisce. Le decisioni di progetto sono in `docs/PROPOSTA.md`.

**Stato:** fase 5 completata (versione 0.5.1).

| Fase | Contenuto | Stato |
|---|---|---|
| 1 | Fondamenta, design system, installazione, aggiornamenti, rilascio | ✅ |
| 2 | Accesso, ruoli, impostazioni azienda | ✅ |
| 3 | Prova di app installabile e notifiche push | — |
| 4–11 | Macchine, ordini di lavoro, magazzino, preventiva, notifiche, dashboard, 3D, GDPR e demo | — |

---

## 1. Modello di installazione

- **Un'installazione per cliente:** una cartella con una copia dell'app, un database MySQL o MariaDB e un file `.env`. L'installazione gestisce una sola azienda.
- **Requisiti del server:** PHP 8.3 e MySQL. Non servono SSH, Docker, Node, processi sempre attivi, `exec` o `proc_open`.
- **Impostazioni dell'azienda:** tabella `settings`, con i valori iniziali presi da `.env`:

  | Chiave | Contenuto | Da `.env` |
  |---|---|---|
  | `company.name` | nome dell'azienda | `MANUTRACK_COMPANY_NAME` |
  | `company.palette` | palette dei colori | `MANUTRACK_PALETTE` |
  | `company.accent` | colore accento personalizzato | `MANUTRACK_ACCENT` |
  | `company.locale` | lingua | — |
  | `company.timezone` | fuso orario | — |
  | `company.logo` | logo dell'azienda | — |

  Le impostazioni mancanti vengono create a ogni aggiornamento, senza toccare quelle già modificate.

## 2. Installazione

### 2.1 Da riga di comando: `php artisan manutrack:install`
- Mostra i requisiti. Se manca un requisito indispensabile si ferma; quelli consigliati generano solo un avviso.
- Chiede, oppure riceve come opzioni: nome dell'azienda, nome, email e password dell'amministratore, palette, generazione delle chiavi push.
- **Regole di validazione:**
  - email valida;
  - password di almeno 10 caratteri, con lettere e numeri;
  - palette tra quelle disponibili.
- **Passi eseguiti:**
  1. chiave dell'applicazione (se manca);
  2. migrazioni;
  3. impostazioni;
  4. allineamento dei dati di sistema;
  5. amministratore, con email verificata e salvata in minuscolo;
  6. chiavi VAPID, se richieste;
  7. registrazione della versione;
  8. file di blocco `storage/app/installed.lock`.
- **Codici di uscita:** 0 = installata; 1 = già installata, requisiti mancanti o errore; 2 = dati non validi.

### 2.2 Dal browser: `/install`
- Finché l'app non è installata, ogni pagina porta a `/install`.
- **La procedura richiede il codice `MANUTRACK_INSTALL_TOKEN` di `.env`:**
  - se il codice non è configurato, la pagina spiega come impostarlo e non mostra il modulo;
  - il codice è confrontato in tempo costante;
  - sono ammessi al massimo 5 tentativi al minuto per indirizzo IP.
- I passi eseguiti sono gli stessi della riga di comando.
- **Al termine:**
  - il codice viene cancellato da `.env` (se il file non è scrivibile, la pagina chiede di farlo a mano);
  - viene mostrata la pagina "Installazione completata" con gli ultimi passi (cron, HTTPS).
- **Blocco:** dopo l'installazione `/install` risponde **404**, sia in lettura sia in invio. Si considera installata un'app se:
  - esiste il file di blocco, **oppure**
  - nel database c'è una versione registrata o almeno un utente.

  Se il file di blocco viene cancellato ma il database dice "installata", il file viene ricreato.
- **Concorrenza:** due installazioni contemporanee sono impedite da un blocco su file.

### 2.3 Sviluppo: `php artisan migrate --seed`
Crea un'installazione di prova con l'amministratore fittizio `admin@example.com` / `manutrack-demo`. In produzione è bloccato, salvo nell'installazione demo.

## 3. Aggiornamenti

### 3.1 Pacchetto di rilascio
- Si crea con `scripts/release.sh` (Linux/macOS) o `scripts/release.ps1` (Windows). La logica comune è in `scripts/release/package.php`.
- **Contiene:**
  - codice;
  - `vendor/` di produzione;
  - frontend compilato;
  - documentazione;
  - `release.json`, scritto e aggiunto allo zip **per ultimo**: contiene versione, data ed elenco dei file con la loro dimensione.
- **Non contiene:** `.env`, dati in `storage/`, test, sorgenti del frontend, strumenti di sviluppo.
- Lo script si rifiuta di procedere se:
  - ci sono modifiche non salvate in git (salvo `--dirty`/`-Dirty` per le prove);
  - `CHANGELOG.md` non ha la sezione della versione;
  - il pacchetto conterrebbe `.env`.

### 3.2 `php artisan manutrack:update`
1. Blocco su file, condiviso con il ripristino: un secondo aggiornamento contemporaneo risponde "in corso".
2. Se la versione del codice è uguale o precedente a quella registrata: "Già aggiornato", nessuna modifica (`--force` riesegue comunque).
3. Copia del database (§4), poi pulizia delle copie vecchie.
4. Modalità manutenzione, con la pagina "Aggiornamento in corso" che si ricarica da sola.
5. Migrazioni.
6. Allineamento dei dati di sistema, idempotente.
7. Cache (route, viste, eventi; la configurazione solo con `MANUTRACK_CACHE_CONFIG=true`) e riavvio della coda.
8. Registrazione della versione e uscita dalla manutenzione. Il comando mostra "versione precedente → nuova".

**In caso di errore:**
- uscita dalla manutenzione: l'app resta utilizzabile con i dati di prima;
- esito "fallito" registrato con il messaggio d'errore;
- avviso mostrato in tutte le pagine e dettagli nella pagina Sistema, con il nome della copia del database.

### 3.3 Aggiornamento automatico dal cron
Ogni minuto il cron controlla, in quest'ordine:
1. aggiornamento automatico attivo (`MANUTRACK_AUTO_UPDATE`);
2. app installata;
3. versione del codice più recente di quella registrata;
4. `release.json` presente e della stessa versione del codice;
5. tutti i file di `release.json` presenti con la dimensione giusta;
6. nessuna modifica ai file da almeno `MANUTRACK_UPDATE_SETTLE_MINUTES` minuti (predefinito 5);
7. la stessa versione non è già fallita, salvo richiesta di ritentare (`manutrack:update --retry`).

Solo se tutti i controlli passano, esegue l'aggiornamento come in §3.2. Il motivo dell'attesa è visibile nella pagina Sistema.

## 4. Copie del database

- **Formato:** scritte in PHP (PDO), senza `mysqldump` né `exec`, in `storage/app/backups/manutrack-AAAAMMGG-HHMMSS-<etichetta>.sql.gz`:
  - SQL compresso, un'istruzione per riga;
  - testi con le sequenze di escape di MySQL, dati non UTF-8 in esadecimale, valori nulli conservati;
  - struttura di tutte le tabelle e dati di tutte tranne cache e sessioni;
  - ultima riga `-- fine`, che serve a riconoscere le copie complete.
- **Protezione:** la cartella è protetta da `.htaccess` (`Require all denied`).
- **Conservazione:** restano solo le ultime `MANUTRACK_BACKUPS_KEEP` copie (predefinito 5).
- **Comandi:**
  - `manutrack:backup [--label=] [--list]`;
  - `manutrack:restore [file] [--force] [--no-safety]`.
- **Il ripristino:**
  1. rifiuta copie incomplete;
  2. fa una copia di sicurezza dello stato attuale;
  3. mette l'app in manutenzione;
  4. elimina tutte le tabelle attuali e ricrea quelle della copia;
  5. segna come fallito un aggiornamento rimasto "in corso" nella copia.

## 5. Cron e code

Una sola voce di cron (`schedule:run` ogni minuto). Tutti i compiti girano nello stesso processo PHP:

| Compito | Frequenza |
|---|---|
| Segnale di vita (mostrato in Sistema) | ogni minuto |
| Aggiornamento automatico (§3.3) | ogni minuto |
| Coda: `queue:work --stop-when-empty --max-time=50`, saltata con `QUEUE_CONNECTION=sync` | ogni minuto, senza sovrapposizioni |
| Pulizia dei job falliti (30 giorni) e dei lotti (7 giorni) | ogni giorno alle 3:30 |

## 6. Interfaccia (fase 1)

- **Struttura:**
  - **PC:** barra laterale con le sezioni (quelle future sono segnate "in arrivo"), barra superiore con ricerca.
  - **Telefono:** barra in basso (Home, Ordini, pulsante centrale Segnala, Avvisi, Sistema), aree sicure di iPhone, altezze `dvh`.
- **Scorciatoie da tastiera:**

  | Tasti | Azione |
  |---|---|
  | `Ctrl/Cmd K` o `/` | palette comandi |
  | `G` poi `D` | Panoramica |
  | `G` poi `S` | Sistema |
  | `?` | elenco delle scorciatoie |
  | `Esc` | chiude finestre e menu |

  Le sequenze per le sezioni future (`G O`, `G M`, `G P`, `G R`, `G N`) si attivano quando le sezioni esistono.
- **Tema:** Scuro (predefinito), Chiaro o Come il dispositivo, salvato sul dispositivo.
- **Pagine:**

  | Pagina | Contenuto |
  |---|---|
  | **Panoramica** | benvenuto con il nome dell'azienda; stato di versione, cron, coda e chiavi push |
  | **Sistema** | versione del codice e del database; stato del cron e della coda; PHP, database, driver immagini, chiavi push; storico degli aggiornamenti (esito, avvio, durata, copia); elenco delle copie; requisiti |
  | **Design system** | token, logo e varianti, icone, caratteri, componenti |

  Dalla fase 2 la pagina Sistema sarà riservata agli amministratori e permetterà di ritentare un aggiornamento.
- **Pagine di errore** (403, 404, 419, 429, 500, 503): autonome, senza database né file compilati, quindi funzionano anche durante un aggiornamento.
- **PWA (base):**
  - manifest dinamico con nome dell'azienda, `display: standalone`, `scope`, `start_url`, colori, icone 192/512 normali e maskable;
  - meta iOS (`apple-mobile-web-app-capable`, barra di stato `black-translucent`, titolo, `apple-touch-icon`).

  Service worker, pagina offline, notifiche: fase 3.

## 7. Requisiti verificati

| Controllo | Esito se manca |
|---|---|
| PHP ≥ 8.3 | errore |
| Estensioni `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `json`, `filter` | errore |
| Estensioni `zip`, `intl`, `bcmath` (o `gmp`) | avviso |
| GD o Imagick | avviso: icone pre-generate per le tre palette (colore personalizzato → palette più vicina) e foto non ridimensionate |
| `storage/` e `bootstrap/cache/` scrivibili | errore |
| `.env` scrivibile | avviso: chiavi e codici da copiare a mano |
| Connessione al database | errore |

## 8. Comandi artisan

| Comando | Scopo |
|---|---|
| `manutrack:install` | installazione |
| `manutrack:update [--auto] [--retry] [--force]` | aggiornamento |
| `manutrack:backup [--label=] [--list]` | copia del database |
| `manutrack:restore [file] [--force] [--no-safety]` | ripristino |
| `manutrack:vapid [--force] [--show]` | chiavi per le notifiche push |
| `manutrack:icons [--palette=] [--path=]` | icone dell'app dal logo |

---

## 9. Accesso (fase 2)

- **Solo su invito**: non esiste la registrazione libera (`/register` risponde 404).
- **Login** con email (non distingue maiuscole e minuscole) e password, con l'opzione "Ricordami".
  - Limite: 5 tentativi al minuto per email e indirizzo IP, 30 al minuto per indirizzo IP; oltre si riceve 429.
  - Ogni accesso, uscita e tentativo fallito finisce nel registro attività; all'accesso si aggiorna "ultimo accesso".
- **Account disattivati**: non possono entrare (messaggio "Questo account è stato disattivato") e una sessione già aperta viene chiusa alla richiesta successiva.
- **Password dimenticata**: link via email (Fortify) valido 60 minuti.
- **Regola delle password**, valida ovunque: almeno 10 caratteri, con lettere e numeri.
- **Verifica in due passaggi** (TOTP, ad esempio Google Authenticator o 1Password):
  - si attiva dal profilo confermando la password, poi si inquadra il QR e si inserisce il primo codice;
  - vengono mostrati 8 codici di recupero, rigenerabili;
  - al login, dopo la password, si chiede il codice o un codice di recupero;
  - un codice già usato non vale due volte;
  - l'amministratore può azzerarla a una persona che ha perso il telefono.
- **Conferma della password** (valida 3 ore) per la pagina Sistema e le sue azioni.
- **Indirizzi**: prima dell'installazione ogni pagina porta a `/install`; dopo, chi non ha fatto l'accesso va a `/login`.

## 10. Ruoli e permessi

| Ruolo | Permessi predefiniti |
|---|---|
| Amministratore | tutti, sempre (non modificabile) |
| Responsabile manutenzione | vedere le persone; macchine; segnalazioni (creare, vedere tutte, accettare); ordini (tutto, compresi costi e chiusura); preventiva; magazzino in lettura e prelievo; indicatori ed esportazioni |
| Tecnico | vedere le macchine; segnalare e vedere tutte le segnalazioni; vedere, creare ed eseguire ordini; vedere la preventiva; magazzino in lettura e prelievo |
| Magazziniere | vedere le macchine; segnalare; vedere gli ordini; magazzino completo; vedere gli indicatori |
| Richiedente | vedere le macchine; segnalare guasti |
| Osservatore | sola lettura di macchine, segnalazioni, ordini (con costi), preventiva, magazzino; indicatori ed esportazioni |

- **Permessi per modulo**: persone, impostazioni, sistema e dati, macchine, segnalazioni, ordini di lavoro, preventiva, magazzino, indicatori. Quelli dei moduli futuri sono già definiti, così i ruoli si possono preparare in anticipo.
- **Matrice ruoli × permessi** (`/ruoli`, solo amministratori): ogni casella si attiva o disattiva subito; "Ripristina predefiniti" riporta un ruolo ai valori di fabbrica.
- **Aggiornamenti**: il sincronizzatore crea ruoli e permessi mancanti; ai ruoli esistenti aggiunge solo i permessi nuovi della versione, senza togliere le modifiche dell'amministratore; garantisce almeno un amministratore attivo.
- **Menu e pagine** seguono i permessi: una voce non consentita non compare e la pagina risponde 403.

## 11. Persone e inviti (`/utenti`)

- **Elenco**: ricerca per nome, email o mansione; filtro per ruolo e stato (attive, disattivate, tutte).
  - PC: tabella con ruolo modificabile, stato, verifica in due passaggi, ultimo accesso e menu azioni.
  - Telefono: schede compatte.
- **Invito** (permesso "Invitare persone…"):
  - email, nome facoltativo e ruolo;
  - email con link valido **7 giorni**; nel database c'è solo l'impronta SHA-256 del codice;
  - un nuovo invito alla stessa email annulla il precedente;
  - non si invita un'email già registrata;
  - gli inviti in attesa si possono inviare di nuovo o annullare.
- **Accettazione** (`/invito/{codice}`, solo per chi non ha fatto l'accesso, 10 richieste al minuto):
  - la persona sceglie nome e password ed entra con il ruolo deciso, con email già verificata;
  - inviti scaduti, usati o annullati mostrano "Invito non valido".
- **Azioni sulle persone**: cambio ruolo, disattivazione e riattivazione, azzeramento della verifica in due passaggi, invio del link per una nuova password.
  - Non si può disattivare se stessi.
  - Non si può togliere il ruolo di amministratore, né disattivare, l'ultimo amministratore attivo.
- **Registro**: ogni azione viene registrata.

## 12. Impostazioni dell'azienda (`/impostazioni/azienda`)

- **Dati generali**: nome dell'azienda, fuso orario, lingua dell'interfaccia (le lingue disponibili sono le cartelle in `lang/`). Lingua e fuso orario valgono per tutte le pagine.
- **Logo del cliente** (non incluso nel codice dell'app):
  - versione principale per fondi chiari e versione facoltativa per il tema scuro;
  - formati PNG, JPEG o WebP fino a 2 MB; SVG rifiutato, perché può contenere script; il tipo del file viene controllato sul contenuto, non sull'estensione;
  - con GD o Imagick l'immagine viene ricodificata in PNG e ridotta a 720×240 px al massimo, il che elimina anche eventuali dati nascosti;
  - salvato sul disco privato e servito da `/marchio/logo` con cache lunga e `nosniff`.
- **Dove compare il logo**:
  - in alto nella barra laterale (PC), nella barra superiore (telefono) e nella pagina di accesso;
  - nel tema scuro si usa la versione scura; se manca, il logo principale appare su una targhetta chiara;
  - senza logo compaiono la M di ManuTrack e il nome dell'azienda;
  - "con ManuTrack" e la versione compaiono in piccolo nella pagina di accesso e in fondo alla barra laterale o alla pagina.
- **Colori**: Grafite e Rame, Acciaio e Ciano, Ardesia e Indaco, **Pistacchio**, oppure **Personalizzata**.
  - La palette personalizzata parte da due colori (principale e chiaro) e calcola le varianti per i due temi:
    - pulsanti con testo bianco ≥ 4,5:1;
    - testi d'accento ≥ 7:1 sul fondo scuro e ≥ 4,5:1 sul chiaro.
  - L'anteprima dal vivo mostra pulsante, stato "Operativa", testo d'accento e la M, con i rapporti di contrasto.
  - **"Prendi i colori dal logo"**: individua il colore scuro e quello chiaro più presenti nel logo, ignorando bianchi, neri e grigi.
- **Icone**: le icone dell'app (192/512, maskable, apple-touch, favicon) si rigenerano per la palette personalizzata e vengono servite da `/marchio/icone/…`; senza GD si usano quelle della preimpostazione più vicina. Il manifest punta alle icone giuste.

## 13. Profilo (`/profilo`)

- **Dati**: nome, email (unica), mansione, telefono.
- **Password**: per cambiarla serve quella attuale.
- **Verifica in due passaggi**: attivazione, conferma, codici di recupero, disattivazione (§9).
- **Altro**: tema, collegamento a Sistema per chi ha il permesso, pulsante "Esci".

## 14. Registro attività (`/registro`, permesso "Vedere il registro")

- Ogni voce registra data e ora, persona, azione, oggetto, dettaglio, modifiche prima → dopo, indirizzo IP e browser.
- Password, codici e segreti non vengono **mai** salvati.
- Ricerca per persona, dettaglio o indirizzo IP; filtro per area (accessi, persone, ruoli, impostazioni, sistema, profilo).
- Le voci non si modificano.

## 15. Pagina Sistema (aggiornamento)

- Riservata a chi ha il permesso "Vedere la pagina Sistema" (di norma solo l'amministratore), con conferma della password.
- **Azioni** (permesso "Applicare aggiornamenti e creare copie"):
  - **Applica ora**: esegue l'aggiornamento in attesa dal browser, con avvio "pannello";
  - **Fai ritentare al cron**: dopo un errore;
  - **Crea una copia ora**.
- L'avviso di aggiornamento in attesa o fallito compare in tutte le pagine solo a chi gestisce il sistema.

## 16. App installabile (fase 3)

- **Manifest** (`/manifest.webmanifest`): nome dell'azienda, icone della palette attiva (anche maskable), avvio a schermo intero (`standalone`), colori del tema.
- **Service worker** (`/sw.js`): generato da Laravel a partire dal manifest di Vite (niente Workbox).
  - Versione = `VERSION` + impronta dei file in cache: cambia a ogni rilascio.
  - Cache: pagina offline, CSS e JavaScript compilati, caratteri latini, icona e favicon.
  - Pagine: prima la rete; senza rete, la pagina **"Sei offline"** (`/offline`), che si ricarica da sola quando torna la connessione.
  - File compilati: prima la cache. Icone e immagini: la cache, aggiornata in background.
  - Mai in cache: chiamate Livewire, push, installazione, aggiornamento, file privati.
  - `/sw.js` e `/offline` non aprono sessioni e non impostano cookie.
- **Nuova versione:** il nuovo service worker resta in attesa. In basso compare "Nuova versione disponibile" con **Aggiorna**; la pagina si ricarica solo su richiesta. Il controllo avviene ogni ora e a ogni ritorno sull'app.
- **Schermate di avvio iPhone:** 11 formati per palette, generati da `manutrack:icons` (per la palette personalizzata si usa la preimpostazione più vicina).

## 17. Procedura guidata (`/app`)

- Tre passi: **Installa**, **Notifiche**, **Prova**. Il dispositivo viene riconosciuto (iPhone/iPad con versione di iOS, Android, PC; browser; app già installata o no).
- **iPhone:** illustrazioni animate di Condividi › Aggiungi alla schermata Home. Avvisi se il browser non è Safari, se iOS è precedente alla 16.4, o se la pagina è aperta in Safari anziché dall'icona.
- **Android e PC:** pulsante di installazione quando il browser lo consente (`beforeinstallprompt`), altrimenti istruzioni dal menu.
- **Notifiche:** il permesso si chiede **solo** dopo il tocco su "Attiva notifiche", mai all'apertura di una pagina. Se è negato, compaiono le istruzioni per riattivarlo sul proprio sistema.
- **Prova:** "Invia notifica di prova" a questo dispositivo, con l'esito.
- In panoramica, su telefono, un riquadro invita all'installazione finché l'app non è installata con le notifiche decise; "Non ora" lo nasconde su quel dispositivo.

## 18. Notifiche push

- Chiavi **VAPID** per installazione, in `.env` (`manutrack:vapid`, oppure in installazione).
- **Iscrizioni** per dispositivo (`push_subscriptions`):
  - endpoint con impronta univoca, chiavi del browser;
  - nome del dispositivo ricavato dal browser (es. "iPhone · Safari");
  - ultimo uso, ultimo invio riuscito, errori consecutivi.
  Una persona può avere più dispositivi.
- **Invio** con `minishlink/web-push`:
  - l'iscrizione si elimina quando il servizio risponde 404/410 o dopo 5 errori consecutivi;
  - funziona anche senza `bcmath`/`gmp`.
- **Contenuto:** titolo, testo, pagina da aprire al tocco, etichetta (sostituisce la notifica precedente con la stessa etichetta), numero sul pallino dell'icona (Badging API, dove disponibile).
- Il tocco sulla notifica porta in primo piano l'app già aperta, altrimenti la apre sulla pagina indicata.
- **Rinnovo:** quando il browser cambia l'iscrizione (`pushsubscriptionchange`), il service worker la aggiorna su `/push/rinnova` presentando quella vecchia.
- **Limiti:** 10 prove al minuto per persona; 30 chiamate al minuto per iscrizioni e rinnovi.
- **Registro attività:** dispositivo aggiunto, dispositivo rimosso.

## 19. Diagnostica notifiche (`/notifiche/diagnostica`)

- Controlli su questo dispositivo, ciascuno con lo stato e cosa fare:
  - HTTPS;
  - service worker supportato e attivo (con versione);
  - app installata;
  - versione di iOS;
  - push supportate;
  - permesso;
  - iscrizione sul dispositivo e sul server;
  - chiavi VAPID;
  - pallino sull'icona.
- **Azioni:**
  - attiva;
  - prova su questo dispositivo o su tutti i propri;
  - disattiva su questo dispositivo;
  - **Copia rapporto** (testo senza chiavi né endpoint completi).
- Elenco dei propri dispositivi iscritti, con l'ultimo invio riuscito e l'ultimo errore, e la rimozione.
- La guida passo passo per i telefoni reali è in [`TEST-DISPOSITIVI.md`](TEST-DISPOSITIVI.md).

## 20. Notifiche push urgenti e normali (fase 4)

- **Urgenti** (guasto o fermo di una macchina di criticità A, che ferma la linea; dalla fase 5 anche le segnalazioni critiche):
  - partono **subito dopo la risposta** a chi ha fatto l'azione (`dispatchAfterResponse`), senza aspettare il cron della coda;
  - la pagina non aspetta l'invio;
  - ai servizi push si chiede la consegna ad alta priorità (`Urgency: high`, durata 6 ore): il telefono si sveglia anche in risparmio energetico.
- **Se l'invio immediato fallisce** per un problema temporaneo (rete, 429, 5xx), i dispositivi non raggiunti passano alla coda normale e vengono ritentati al giro di cron successivo. I dispositivi scaduti (404/410) vengono eliminati come prima.
- **Normali** (tutto il resto, per esempio una macchina non critica in guasto, o una macchina di nuovo operativa):
  - vanno nella coda su database, che il cron svuota ogni minuto;
  - `Urgency: normal`, durata 24 ore.
- **Destinatari degli avvisi sulle macchine:** chi ha il permesso "Accettare o respingere le segnalazioni" (responsabili e amministratori), attivo, esclusa la persona che ha fatto il cambio.
- **Pagina Sistema:** indica come il server libera la pagina prima dell'invio urgente:
  - PHP-FPM o LiteSpeed: la connessione si chiude prima dell'invio;
  - altrimenti la risposta arriva comunque, ma il browser può attendere qualche secondo.
- Con `QUEUE_CONNECTION=sync` anche le notifiche normali partono subito.

## 21. Ubicazioni (`/macchine/ubicazioni`)

- Albero a tre livelli: **sito › area › linea**. Il tipo dipende dal livello.
- Nome unico tra i fratelli, codice e descrizione facoltativi.
- Lo spostamento di un'ubicazione resta allo stesso livello e aggiorna il percorso di tutte le ubicazioni contenute.
- Non si elimina un'ubicazione che contiene altre ubicazioni o macchine.
- Il conteggio delle macchine porta all'elenco già filtrato.

## 22. Macchine (`/macchine`)

- **Dati:**
  - codice unico, nome, categoria, ubicazione (a qualunque livello);
  - macchina principale (sottogruppi, senza cicli);
  - matricola, costruttore, modello, anno, data di installazione, garanzia;
  - costo d'acquisto (visibile solo con "Vedere i costi"), note.
- **Criticità A/B/C:**
  - A ferma la linea, B rallenta, C non blocca;
  - decide l'urgenza degli avvisi.
- **Stati:** operativa, in guasto, in manutenzione, ferma, dismessa, sempre con icona e testo.
  - Lo stato si cambia dalla scheda (permesso "Cambiare lo stato"), con una nota facoltativa.
  - Il cambio si registra nel registro attività e avvisa come al paragrafo 20.
- **Elenco:**
  - ricerca su codice, nome, matricola, costruttore e modello;
  - filtri per ubicazione (compresi i livelli sotto), categoria, stato (pastiglie con i conteggi) e criticità;
  - quattro ordinamenti;
  - tabella su PC, schede su telefono, con i filtri richiudibili;
  - scelta delle macchine per le etichette (anche "scegli tutte", fino a 500).
- **Scheda:** foto principale, stato e da quando, ubicazione, sottogruppi, contatori, foto, documenti, dati tecnici, QR.
- **Eliminazione:**
  - solo senza sottogruppi;
  - porta via contatori, foto e documenti;
  - per conservare lo storico si consiglia lo stato "Dismessa".
- **Panoramica:** chi vede le macchine trova l'elenco di quelle ferme o in guasto, prima le critiche.

### 22.1 Permessi nuovi

| Permesso | Predefinito per |
|---|---|
| `assets.status` Cambiare lo stato | responsabile, tecnico |
| `assets.readings` Registrare le letture dei contatori | responsabile, tecnico |
| `assets.attach` Aggiungere foto e documenti | responsabile, tecnico |

Con l'aggiornamento vengono aggiunti ai ruoli esistenti senza toccare le altre modifiche dell'amministratore. "Creare e modificare" (`assets.manage`) comprende anche l'importazione, le categorie e l'eliminazione.

## 23. Contatori

- Uno o più per macchina: nome, unità (h, cicli, pezzi, km… o libera), progressivo sì/no.
- **Letture:**
  - numeri anche all'italiana ("1.250,5");
  - mai negative né nel futuro;
  - nei contatori progressivi una lettura non può essere più bassa della precedente né più alta della successiva, salvo la casella "contatore sostituito o azzerato".
- Una lettura con data passata non cambia il valore attuale.
- Storico con data, valore e persona.
- Saranno la base dei piani preventivi a contatore (fase 7).

## 24. Foto e documenti

- **Disco privato** (`storage/app/private/attachments/...`), nomi casuali. Si scaricano solo da `/allegati/{id}`, che controlla il permesso sull'oggetto (macchine: "Vedere le macchine").
- **Foto** (JPEG, PNG, WebP): vedi il paragrafo 27, che vale per macchine, segnalazioni e ordini di lavoro.
  - Senza GD né Imagick si salvano senza ridimensionare, ma dai JPEG si tolgono comunque EXIF e GPS.
  - La prima foto è la principale; si può cambiare.
- **Documenti:** pdf, doc(x), xls(x), odt, ods, txt, csv, immagini, dwg, dxf.
- **Limite:** il minore tra `MANUTRACK_UPLOAD_MAX_MB` (20) e i limiti PHP del server (`upload_max_filesize`, `post_max_size`), mostrato sotto i documenti.
- **Intestazioni dei file serviti:**
  - immagini e PDF si aprono nel browser, gli altri file si scaricano;
  - `nosniff` e cache solo privata;
  - le immagini hanno anche una CSP `sandbox`.
  - Il service worker non mette mai in cache gli allegati.
- Il tecnico elimina solo i file che ha caricato lui; chi gestisce le macchine li elimina tutti.

## 25. Codici QR ed etichette

- Ogni macchina ha un codice casuale (`qr_token`, 24 caratteri) che non rivela l'id. Il QR contiene `https://<dominio>/q/<codice>`:
  - chi non ha fatto l'accesso passa dal login e poi arriva alla scheda;
  - un codice sconosciuto porta all'elenco con un avviso.
- "Genera un nuovo codice" invalida le etichette stampate (con conferma).
- QR in SVG generato in PHP (nitido in stampa, senza GD). Dalla scheda si scarica anche il file SVG.
- **Etichette** (`/macchine/etichette`): fogli A4 in tre formati.
  - piccole: 24 da 70 × 37 mm;
  - medie: 8 da 105 × 74 mm;
  - grandi: 4 da 105 × 148,5 mm.
  - Ogni etichetta ha QR, codice, nome, ubicazione e nome dell'azienda.
  - "Salta le prime" serve a riusare fogli già in parte usati.
  - Si stampa al 100% senza margini.
- Sui telefoni il QR si inquadra con la fotocamera. Su Android, con l'app installata, il link si apre nell'app; su iPhone si apre in Safari. Lo scanner dentro l'app arriva con la segnalazione da QR (fase 5).

## 26. Importazione (`/macchine/importa`)

- **File:**
  - Excel `.xlsx` (serve l'estensione PHP `zip`) oppure CSV;
  - separatore `;` `,` o tabulazione e codifica UTF-8 o Windows-1252, riconosciuti da soli;
  - fino a 5000 righe.
  - Modello CSV scaricabile.
- **Colonne:** riconosciute dal nome, anche con varianti (`codice`, `nome`, `categoria`, `ubicazione`, `macchina_padre`, `matricola`, `costruttore`, `modello`, `anno`, `data_installazione`, `criticita`, `stato`, `costo_acquisto`, `garanzia_fino_al`, `note`). Le colonne sconosciute vengono ignorate e segnalate.
- **Anteprima:**
  - quante macchine nuove, da aggiornare e righe con errori (con numero di riga e motivo);
  - ubicazioni e categorie che verranno create;
  - prime righe.
  - Nulla si salva prima della conferma.
- **Regole:**
  - le macchine si riconoscono dal codice (senza distinguere maiuscole e minuscole);
  - in aggiornamento le celle vuote non cancellano i dati;
  - le ubicazioni si scrivono "Sito > Area > Linea" e si creano se mancano;
  - la macchina padre può essere nello stesso file;
  - date gg/mm/aaaa (anche le date di Excel), numeri all'italiana.
- **Conferma:** tutto in una transazione, una voce nel registro attività.

## 27. Foto in WebP (fase 5, per tutta l'app)

- **Sul telefono, prima dell'invio** (`resources/js/media/photo.js`):
  - lato lungo al massimo 1600 px e compressione;
  - WebP (qualità 0,8) se il browser lo sa generare, altrimenti JPEG (0,82), come su Safari per iPhone;
  - le foto HEIC dell'iPhone diventano JPEG, perché Safari le sa aprire;
  - la ricodifica applica l'orientamento e toglie EXIF e GPS.
  - Vale per la segnalazione rapida e per i campi foto delle schede di macchine e ordini.
  - Se il browser non riesce ad aprire la foto, si invia l'originale.
- **Sul server** (`PhotoProcessor`):
  - sempre WebP qualità 80, lato lungo 1600 px, miniatura WebP da 400 px per gli elenchi;
  - metadati tolti, compresi EXIF e GPS;
  - orientamento EXIF letto in PHP puro, quindi funziona anche senza l'estensione `exif`;
  - limite di 50 megapixel.
- **Senza supporto WebP** in GD/Imagick, oppure con `MANUTRACK_PHOTO_FORMAT=jpeg`, le foto si salvano in JPEG ridotto. Lo segnalano l'installazione (requisito "Foto in formato WebP") e la pagina Sistema (voce "Foto").
- **Senza GD né Imagick** le foto si salvano com'erano, togliendo comunque EXIF e GPS dai JPEG.
- **File non validi o HEIC:**
  - un file che non è un'immagine dà l'errore "Il file non è una foto leggibile" e non si salva nulla;
  - una foto HEIC che il server non sa convertire **non si perde**: si conserva tra i documenti, con un avviso che spiega cosa fare.
- **`php artisan manutrack:foto-ottimizza`** converte le foto già salvate nel formato attuale.
  - `--prova` mostra lo spazio risparmiato senza cambiare nulla;
  - `--limite=N` limita il numero di foto.
  - Per ogni foto scrive i file nuovi e li rilegge per verificarli. Solo dopo aggiorna l'archivio e cancella l'originale; in caso di errore l'originale resta.

## 28. Segnalazioni

- **Ciclo di vita:** `nuova` (da valutare) → `accettata` (nasce l'ordine di lavoro), `respinta` (con motivo) oppure `duplicata` (di un'altra, eredita il suo ordine).
- **Numerazione:** `SEG-AAAA-NNNNN` per anno, con blocco di riga (tabella `sequences`).
- **Contenuto:**
  - macchina, oppure ubicazione se non è una macchina;
  - cosa succede;
  - macchina ferma sì/no;
  - urgenza bassa/media/alta/critica;
  - foto (fino a 6);
  - autore;
  - ora di scrittura sul telefono (`reported_at`) e ora d'arrivo.
- **Macchina ferma:** la macchina passa "in guasto" e si apre un **fermo** dall'ora della segnalazione.
- **Urgente** = priorità critica, oppure macchina ferma di criticità A. Chi valuta riceve una notifica **subito dopo la risposta**, ad alta priorità (vedi §20). Le altre vanno in coda.
- **Chi le vede:**
  - chi segnala vede sempre le proprie;
  - tutte con "Vedere tutte le segnalazioni";
  - valutare richiede "Accettare o respingere".
- **Elenco:** chi valuta parte da quelle da valutare, con le urgenti in cima; filtri per stato e ricerca.
- **Valutazione:**
  - accettando si scelgono priorità, tipo, titolo, assegnatari, scadenza e checklist;
  - chi ha segnalato riceve l'esito (presa in carico, respinta con il motivo, già nota).

## 29. Segnalazione rapida (`/segnala`)

Pulsante centrale della barra in basso, scheda macchina, panoramica. Pensata per il telefono, in pochi tocchi:
1. **Macchina:**
   - **Inquadra il QR** (fotocamera dal vivo, lettore a tutto schermo);
   - "Usa una foto" del QR;
   - ricerca per codice o nome;
   - le ultime segnalate;
   - "Non è una macchina" con l'ubicazione.
   - Dal QR stampato (`/q/<codice>`) chi può solo segnalare arriva direttamente qui con la macchina scelta.
2. **Cosa succede:**
   - testo con frasi rapide;
   - "La macchina è ferma?" con due pulsanti grandi;
   - urgenza;
   - foto (preparate sul telefono, §27).
   - Avviso rosso quando la segnalazione sarà urgente.
3. **Invio:** numero della segnalazione, oppure "Salvata sul telefono".

**Lettore QR:** `BarcodeDetector` dove esiste (Chrome su Android), altrimenti `jsQR`, caricato solo quando serve e tenuto in cache per l'uso offline. La fotocamera si spegne appena il codice è letto.

**Senza rete (invio differito):**
- La pagina resta disponibile offline:
  - il service worker la salva a ogni visita (cache `mt-pages`) e la prepara una volta per sessione;
  - contiene l'elenco delle macchine, quindi ricerca e QR funzionano senza rete.
- La segnalazione si salva **prima** in IndexedDB, con un identificativo creato sul telefono, poi si prova l'invio.
- **Android:** Background Sync, quindi parte anche ad app chiusa.
- **iPhone (e come ripiego):** parte all'apertura dell'app, al ritorno della rete o quando l'app torna in primo piano.
- In ogni pagina un avviso indica le segnalazioni in attesa, con "Invia ora".
- **Doppioni:** il server riconosce un invio ripetuto dall'identificativo e non duplica.
- **Ora:** si conserva quella di scrittura, se plausibile (non futura, non più vecchia di 30 giorni).
- **Mai cancellate da sole:**
  - quelle rifiutate per un motivo che non si risolve riprovando restano "da controllare" in fondo a `/segnala`, con "Riprova" ed "Elimina";
  - quelle con la sessione scaduta partono dopo il nuovo accesso;
  - quelle scritte da un'altra persona sullo stesso telefono partono quando quella persona accede.
- **Sicurezza dell'invio** (`POST /segnalazioni/invia`):
  - è escluso dal controllo CSRF perché lo usa anche il service worker;
  - accetta solo richieste autenticate dello stesso sito con l'intestazione `X-ManuTrack: 1`, che un altro sito non può inviare senza CORS;
  - origine verificata, cookie di sessione SameSite=Lax;
  - limite di 30 invii al minuto per persona.
- **All'uscita** la risposta invia `Clear-Site-Data: "cache"` e la pagina cancella la cache `mt-pages`: sul telefono condiviso non restano dati della persona uscita. Le segnalazioni non inviate restano a chi le ha scritte.

## 30. Ordini di lavoro (`/ordini`)

- **Numerazione:** `OdL-AAAA-NNNNN`.
- **Tipi:** correttivo, preventivo, miglioramento.
- **Priorità:** bassa, media, alta, critica.
- **Ciclo di vita** (come da proposta):

| Da | A | Permesso | Note |
|---|---|---|---|
| aperto | pianificato | Pianificare e assegnare | automatico assegnando qualcuno |
| aperto, pianificato | in corso | Eseguire (ed essere assegnatario, o poter assegnare) | data di inizio, parte il cronometro di chi inizia, macchina in guasto/ferma → "in manutenzione" |
| aperto, pianificato | annullato | Pianificare e assegnare | motivo obbligatorio |
| in corso | in attesa | Eseguire | motivo: ricambi, fornitore esterno, produzione; cronometri fermati |
| in attesa | in corso | Eseguire | "Riprendi" |
| in corso | completato | Eseguire | checklist obbligatoria completa, note di chiusura, codici guasto, cronometri fermati, macchina di nuovo operativa (a scelta) e fermo chiuso |
| completato | chiuso | Chiudere e riaprire | il costo orario del momento resta fissato |
| completato | in corso | Chiudere e riaprire | riapertura, motivo obbligatorio, contatore riaperture |

  Nessun altro passaggio è possibile con le azioni normali: il ciclo si controlla nell'azione, non solo nei permessi. L'unica eccezione è "Correggi stato" (§30.2).
- **Prendo io:** chi esegue gli ordini può prendere in carico un ordine senza assegnatari.
- **Assegnatari:** solo persone che possono eseguire gli ordini. Chi viene aggiunto riceve una notifica, urgente se l'ordine è critico.
- **Checklist:**
  - voci a spunta o con un valore misurato (con unità);
  - obbligatorie o facoltative;
  - da modelli (Impostazioni manutenzione) o aggiunte al momento.
- **Tempi:**
  - cronometro (uno solo acceso per persona: avviarne uno ferma quello su un altro ordine; sotto i 30 secondi non si conserva);
  - inserimento a mano (minuti e giorno);
  - si elimina il proprio tempo, oppure tutti con "Pianificare e assegnare".
- **Fermo macchina:** aperto dalla segnalazione o a mano, chiuso al completamento, orari correggibili. È la base di MTTR e MTBF (fase 9).
- **Codici guasto:**
  - problema, causa e rimedio, scelti al completamento;
  - gestiti nelle impostazioni;
  - quelli già usati si disattivano, non si cancellano.
- **Foto, commenti e storico:**
  - foto dell'ordine e della segnalazione d'origine;
  - commenti e cambi di stato in un'unica cronologia.
- **Costi** (visibili con "Vedere i costi"):
  - manodopera = minuti × costo orario;
  - più i ricambi prelevati per l'ordine, al costo a cui sono usciti (al netto dei resi, §34.5);
  - più i costi esterni.
- **Elenco:**
  - schede Da fare, In corso, Da chiudere, Chiusi, Tutti, con i conteggi;
  - "Solo i miei", attivo di default per chi non assegna;
  - filtri per priorità, tipo e macchina, ricerca;
  - ordinamento per urgenza e scadenza;
  - avviso degli ordini scaduti.
- **Scorciatoie da tastiera** (PC): `N` nuovo ordine, `F` cerca, `M` solo i miei, `G O` vai agli ordini, `G E` vai alle segnalazioni.

### 30.1 Permessi

| Permesso | Amministratore | Responsabile | Tecnico | Magazziniere | Richiedente | Osservatore |
|---|---|---|---|---|---|---|
| Segnalare | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Vedere tutte le segnalazioni | ✓ | ✓ | ✓ | solo proprie | solo proprie | ✓ |
| Valutare le segnalazioni | ✓ | ✓ | — | — | — | — |
| Vedere gli ordini | ✓ | ✓ | ✓ | ✓ | — | ✓ |
| Creare ordini | ✓ | ✓ | ✓ (assegnati a sé) | — | — | — |
| Eseguire | ✓ | ✓ | solo assegnati, o "Prendo io" | — | — | — |
| Assegnare, pianificare, annullare | ✓ | ✓ | — | — | — | — |
| Chiudere e riaprire | ✓ | ✓ | — | — | — | — |
| Vedere i costi | ✓ | ✓ | — | — | — | ✓ |
| Commentare | ✓ | ✓ | ✓ | — | — | — |
| Impostazioni manutenzione (nuovo) | ✓ | ✓ | — | — | — | — |

È verificata dai test `tests/Feature/Maintenance/RolePermissionsTest.php`, una riga per ruolo.

### 30.2 Correggi stato (solo amministratore, dalla 0.5.1)

- **Chi:** solo il ruolo Amministratore (policy `correctStatus`). Tutti gli altri ruoli, responsabile compreso, restano nel ciclo normale.
- **Cosa fa:** porta un ordine in **qualunque stato**, anche dopo la chiusura o l'annullamento. Il motivo è obbligatorio.
- **Dove resta traccia:**
  - nello storico dell'ordine, segnato "correzione";
  - nel registro attività, con stato, date di completamento e chiusura, e fermi prima e dopo.
- **Campi resi coerenti:**
  - stati attivi: date di completamento e chiusura azzerate;
  - completato o chiuso: data di completamento modificabile, ma non prima dell'inizio del lavoro; alla chiusura si fissa il costo orario;
  - in attesa: motivo obbligatorio;
  - i cronometri si fermano se l'ordine non è in corso.
- **Fermi macchina collegati** (dell'ordine o della sua segnalazione):
  - "Automatico" chiude i fermi aperti se l'ordine diventa completato, chiuso o annullato;
  - "Chiudi" li chiude alla data di completamento;
  - "Riapri" riapre l'ultimo fermo chiuso, se la macchina non ne ha già uno aperto;
  - "Lascia com'è" non li tocca.
  - In ogni caso, se cambia la data di completamento, i fermi che si erano chiusi con il completamento si spostano con lei.
- Lo stato della macchina non cambia da solo: si corregge dalla sua scheda.

## 31. Impostazioni manutenzione (`/ordini/impostazioni`)

- **Modelli di checklist:**
  - una voce per riga;
  - `Pressione [bar]` crea una voce con un valore da misurare;
  - `?` all'inizio rende la voce facoltativa.
- **Codici guasto** per problema, causa e rimedio.
- **Costo orario** della manodopera.

## 32. Panoramica e scheda macchina

- **Panoramica:**
  - "Segnala un guasto" in evidenza sul telefono;
  - segnalazioni da valutare, con le urgenti in cima;
  - "I miei ordini";
  - ricambi sotto scorta, per chi gestisce il magazzino (dalla 0.6.0);
  - macchine ferme.
- **Scheda macchina:**
  - "Segnala un guasto";
  - **storico interventi** (ordini e segnalazioni aperte) con le ore di fermo degli ultimi 90 giorni;
  - "Nuovo ordine" per quella macchina;
  - **ricambi della macchina** (distinta) con giacenza e stato, per chi vede il magazzino (dalla 0.6.0).

## 33. Account dimostrativi (dalla 0.5.1)

- Gli account dimostrativi (`operatore@`, `responsabile@`, `tecnico1@`, `tecnico2@`, `magazzino@` (dalla 0.6.0), `admin@example.com`, password `manutrack-demo`) li crea **solo** il seeder di sviluppo (`php artisan migrate --seed`). Fuori da produzione, oppure con `MANUTRACK_DEMO=true`, li creano anche `DemoMaintenanceSeeder` e `DemoInventorySeeder`.
- `manutrack:install` e l'installazione dal browser creano soltanto l'amministratore indicato, senza dati dimostrativi. Lo verificano i test `tests/Feature/Setup/DemoAccountsTest.php`.
- I seeder dimostrativi non fanno nulla in produzione, salvo installazione dimostrativa.
- **Avviso:** se in produzione (non dimostrativa) esistono account `@example.com`, `@example.org` o `@example.net`, la pagina Sistema li elenca e invita a disattivarli o a dar loro un indirizzo vero.

## 34. Magazzino ricambi (`/magazzino`, fase 6)

### 34.1 Ricambi
- **Anagrafica:** codice univoco, nome, categoria, unità (pz, m, kg, l…), costruttore e suo codice, descrizione, scorta minima, quantità da riordinare, attivo/disattivato.
- **Giacenza:** per magazzino, con lo scaffale; il totale è sempre la somma dei magazzini.
- **Costo medio ponderato:** solo i carichi con un costo lo cambiano: `(giacenza × costo medio + quantità × costo del carico) / (giacenza + quantità)`.
- **Stato:** disponibile, sotto scorta (giacenza ≤ scorta minima), esaurito.
- **Elenco:** filtri per stato della scorta (con i conteggi), magazzino, categoria, disattivati; ricerca per codice, nome, costruttore e codice del costruttore.
- **Scheda:**
  - giacenze per magazzino con lo scaffale;
  - fornitori del ricambio (codice, prezzo, giorni di consegna, preferito);
  - macchine su cui è montato (distinta);
  - ultimi movimenti.
- Le quantità non si scrivono mai a mano: cambiano solo con i movimenti. Anche la giacenza iniziale di un ricambio nuovo è un carico.

### 34.2 Movimenti
| Tipo | Chi | Effetto |
|---|---|---|
| Carico | gestisce il magazzino | entra merce, con fornitore, riferimento (DDT o fattura) e costo; aggiorna il costo medio |
| Prelievo | può muovere il magazzino | esce merce al costo medio, di solito per un ordine di lavoro |
| Reso | può muovere il magazzino | torna merce prelevata per un ordine e non usata, al costo a cui era uscita, mai più del prelevato |
| Rettifica | gestisce il magazzino | dopo una conta: la giacenza diventa quella contata; motivo obbligatorio, nel registro attività |
| Trasferimento | gestisce il magazzino | da un magazzino a un altro: due movimenti con lo stesso codice, totale invariato |

- **Regole:**
  - la giacenza non va mai sotto zero;
  - ogni movimento è una transazione: si bloccano la riga del ricambio e quella della giacenza (`SELECT … FOR UPDATE`, sempre in quest'ordine), si fanno i controlli, si scrivono giacenza, totale e movimento, poi si conferma. Due prelievi contemporanei non possono prendere lo stesso pezzo;
  - anche i controlli che dipendono da altri dati si fanno a righe bloccate: il reso rilegge quanto prelevato per l'ordine, il prelievo rilegge lo stato dell'ordine;
  - il trasferimento mette i due movimenti in un'unica transazione; la rettifica scrive movimento e registro attività insieme;
  - l'avviso di scorta minima parte solo dopo la conferma della transazione;
  - verificato con processi PHP separati che muovono lo stesso ricambio nello stesso istante (`tests/Feature/Inventory/ConcurrencyTest.php`), su MariaDB e MySQL 8 (matrice CI);
  - la rettifica si rifiuta se la giacenza è cambiata mentre si contava;
  - ogni movimento registra chi, quando e la giacenza risultante;
  - i movimenti non si modificano né si cancellano: un errore si corregge con un movimento opposto;
  - in un magazzino disattivato non entra nulla.
- **Registro movimenti** (`/magazzino/movimenti`) con filtri per tipo, magazzino, ricambio e date.

### 34.3 Magazzini e fornitori
- **Magazzini** (`/magazzino/magazzini`): nome e codice. Di solito ne basta uno; altri servono per armadi a bordo linea o furgoni. Un magazzino con giacenza non si può disattivare.
- **Fornitori** (`/magazzino/fornitori`): ragione sociale, referente, email, telefono, sito, note, con i ricambi collegati.

### 34.4 Scorta minima e riordino
- Quando la giacenza totale **scende** alla scorta minima, chi gestisce il magazzino riceve una notifica (in coda, non urgente); chi ha fatto il movimento non la riceve. L'avviso parte una volta sola, finché la giacenza non risale.
- **Da riordinare** (`/magazzino/riordino`):
  - ricambi alla scorta minima o sotto, raggruppati per fornitore preferito;
  - quantità proposta = scorta minima + quantità da riordinare − giacenza (se la quantità da riordinare è vuota, vale quanto la scorta minima);
  - "Copia l'elenco" per incollarlo in una mail, oppure stampa.
- In panoramica, chi gestisce il magazzino vede i ricambi sotto scorta.

### 34.5 Ricambi nell'ordine di lavoro
- Sezione **Ricambi** nella scheda dell'ordine.
- **Chi preleva:** chi può eseguire l'ordine (assegnatario, o chi può assegnare) **e** può muovere il magazzino. L'ordine deve essere attivo o completato (non chiuso né annullato).
- **Come:**
  - senza scrivere nulla si vede la distinta della macchina dell'ordine; altrimenti si cerca per codice o nome;
  - si propone il magazzino con più pezzi.
- **Reso:** "Rendi" per ogni ricambio, fino alla quantità prelevata; torna nel magazzino dell'ultimo prelievo.
- Il costo dei ricambi (prelievi meno resi) entra nei **costi dell'ordine**.
- Il magazziniere può anche prelevare per un ordine dalla scheda del ricambio, indicando il numero dell'ordine.

### 34.6 Distinta ricambi per macchina
- Dalla scheda del ricambio: "Usato sulle macchine", con la quantità montata. La modifica chi gestisce il magazzino o le macchine.
- Nella scheda macchina: elenco dei ricambi con giacenza e stato.

### 34.7 Importazione (`/magazzino/importa`)
- CSV o Excel con anteprima e conferma, come per le macchine (§26). Il modello CSV si scarica dalla pagina.
- **Colonne:** `codice`, `nome` (obbligatorie), `categoria`, `unita`, `costruttore`, `codice_costruttore`, `descrizione`, `scorta_minima`, `quantita_riordino`, `fornitore`, `codice_fornitore`, `prezzo_fornitore`, `magazzino`, `giacenza`, `costo_unitario`, `scaffale`.
- **Regole:**
  - i ricambi esistenti si riconoscono dal codice e si aggiornano; le celle vuote non cancellano nulla;
  - la giacenza vale **solo per i ricambi nuovi** e diventa un carico iniziale; per quelli esistenti si ignora (con avviso) e si usa una rettifica;
  - i fornitori mancanti si creano; i magazzini devono esistere (vuoto = il primo).
- La lettura dei file (CSV di Excel in italiano, XLSX) è in comune con l'importazione delle macchine.

### 34.8 Permessi

| Permesso | Amministratore | Responsabile | Tecnico | Magazziniere | Richiedente | Osservatore |
|---|---|---|---|---|---|---|
| Vedere il magazzino | ✓ | ✓ | ✓ | ✓ | — | ✓ |
| Prelevare (e rendere) | ✓ | ✓ | ✓ | ✓ | — | — |
| Carichi, rettifiche, trasferimenti, anagrafiche, riordino, importazione | ✓ | — | — | ✓ | — | — |
| Vedere costi e valori | ✓ | ✓ | — | ✓ | — | ✓ |

- "Vedere costi e valori" vale per chi gestisce il magazzino o vede i costi degli ordini.
- Il prelievo dall'ordine richiede anche di poter eseguire quell'ordine: il magazziniere non esegue ordini e preleva dalla scheda del ricambio.
- I permessi si cambiano per ruolo da `/ruoli`, come gli altri.
- Verificati dai test `tests/Feature/Inventory/RolePermissionsTest.php`, una riga per ruolo.

## 35. Pacchetto di rilascio senza dati dimostrativi (dalla 0.6.1)

- `scripts/release.sh` e `release.ps1` tolgono `database/seeders` e `database/factories` prima di Composer, così non finiscono nemmeno nell'autoload.
- `scripts/release/package.php` si rifiuta di creare il pacchetto se trova seeder, factory, test, `.env`, un database SQLite, la password degli account di prova o i nomi dei seeder dimostrativi nel codice, o seeder nell'autoload. Lo verifica `tests/Feature/Setup/ReleasePackageTest.php`.
- Restano nel pacchetto solo esempi di formato: le due righe del modello CSV scaricabile ("Esempio da cancellare") e gli esempi nei suggerimenti dei campi.

