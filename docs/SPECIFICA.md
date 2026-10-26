# Specifica funzionale di ManuTrack

Documento scritto man mano, fase per fase. Descrive cosa fa l'app dal punto di vista di chi la usa e di chi la gestisce. Le decisioni di progetto sono in `docs/PROPOSTA.md`.

**Stato:** fase 1 completata (versione 0.1.0).

| Fase | Contenuto | Stato |
|---|---|---|
| 1 | Fondamenta, design system, installazione, aggiornamenti, rilascio | ✅ |
| 2 | Accesso, ruoli, impostazioni azienda | — |
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
