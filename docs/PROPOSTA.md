# ManuTrack: proposta di progetto (revisione 2, approvata)

Stato: **approvata** con le correzioni sotto. Sviluppo in corso dalla fase 1. Palette: Grafite e Rame, con Acciaio e Ciano e Ardesia e Indaco come preimpostazioni.

### Correzioni approvate (prevalgono sul resto del documento)
1. **Aggiornamento automatico dal cron, solo su caricamenti completi.**
   - Lo script di rilascio scrive per ultimo il file `release.json` (versione, data, hash). Senza questo file il cron non aggiorna.
   - Lock su file (`flock`) contro le esecuzioni doppie.
   - Attesa di almeno `MANUTRACK_UPDATE_SETTLE_MINUTES` (predefinito 5) dall'ultima modifica dei file dell'app.
   - Se l'aggiornamento fallisce, l'app esce dalla manutenzione e resta utilizzabile. L'errore viene salvato e mostrato all'amministratore in un avviso e nella pagina Sistema. Il cron non riprova sulla stessa versione finché l'amministratore non lo chiede.
2. **Copia del database prima di ogni aggiornamento**, fatta in PHP (PDO, senza `exec`/`mysqldump`), in `storage/app/backups/` compressa gzip. `php artisan manutrack:restore` ripristina una copia, `manutrack:backup` la crea a mano. Restano solo le ultime `MANUTRACK_BACKUPS_KEEP` copie (predefinito 5).
3. **Installazione dal browser una sola volta.**
   - Dopo il primo completamento si blocca per sempre con `storage/app/installed.lock`, registrato anche nel database.
   - Rifiuta di ripartire se l'app risulta già installata, cioè se esiste il lock, la tabella `users` ha righe o la versione è registrata.
   - Le sue rotte rispondono 404 quando l'app è installata.
4. **Requisiti:** l'installazione verifica GD o Imagick. Se mancano entrambi, l'app usa le icone PNG pre-generate incluse nel pacchetto per le tre preimpostazioni, e un colore personalizzato usa le icone della preimpostazione più vicina. Le foto vengono salvate senza ridimensionamento, con un avviso nella pagina Sistema.
Anteprima dello stile: `docs/anteprima/stile.html` (da aprire nel browser).

### Cosa cambia rispetto alla revisione 1
- **Un'installazione per cliente**: ogni azienda ha la sua cartella con una copia dell'app e il suo database MySQL. Sono stati eliminati i moduli Tenancy e Platform, `tenant_id`, i global scope, i test di isolamento tra aziende, i ruoli di piattaforma e l'appartenenza di un utente a più aziende.
- Le impostazioni dell'azienda (nome, logo, lingua, colori) si gestiscono da una schermata dell'app, con valori iniziali presi da `.env`.
- Nuovi strumenti: installazione (`manutrack:install` e installazione guidata via web), pacchetto di rilascio già compilato (script per Linux/macOS e Windows), `manutrack:update`, file `VERSION` e `CHANGELOG.md`.
- **Hosting condiviso con DirectAdmin**: niente SSH, Docker o Node sul server, una sola voce di cron, nessun processo sempre attivo.
- Una prova minima di app installabile e notifica push di prova viene anticipata alla **fase 3**.
- La demo è un'installazione separata, su richiesta, con dati fittizi e azzeramento notturno.
- "Preventivo in scadenza" significa manutenzione preventiva in scadenza. I preventivi economici dei fornitori restano fuori dal primo rilascio.

---

## 1. Architettura

**Monolite Laravel 12** (PHP 8.3, MySQL 8 o MariaDB 10.6+). Un'installazione serve una sola azienda e ha un solo database.

```
domains/cliente.it/
├── manutrack/            ← copia dell'app (codice, vendor/, build già pronta)
│   ├── .env              ← unico file diverso tra installazioni (con storage/)
│   ├── storage/          ← file caricati, log, cache: mai sovrascritti dagli aggiornamenti
│   └── public/           ← unica cartella esposta al web
└── public_html  → manutrack/public
```

| Modulo | Contenuto |
|---|---|
| `Setup` | installazione, verifica requisiti, aggiornamento, versione, impostazioni azienda |
| `Access` | accesso, 2FA, utenti, inviti, ruoli e permessi |
| `Assets` | ubicazioni, categorie, macchine, contatori, QR, allegati, layout 3D |
| `Maintenance` | segnalazioni, ordini di lavoro, checklist, tempi, fermi, codici guasto |
| `Preventive` | piani a tempo e a contatore, generazione automatica, calendario |
| `Inventory` | ricambi, magazzini, giacenze, movimenti, fornitori |
| `Notifications` | preferenze, push, email per ruolo, centro notifiche, diagnostica |
| `Reporting` | KPI, storico macchina, esportazioni CSV/XLSX |
| `Compliance` | registro attività, esportazione e cancellazione dei dati (GDPR) |

Struttura: `app/Models`, `app/Livewire/<Modulo>`, `app/Actions/<Modulo>` (logica di business in classi azione riusate da Livewire, endpoint JSON e comandi), `app/Policies`, `app/Enums` (stati, priorità e tipi con etichetta, colore e icona), `app/Notifications`, `app/Jobs`, `app/Console/Commands`.

### Impostazioni dell'azienda
- Tabella `settings` (chiave/valore con cache). Se un valore manca si usa quello di `.env` (`MANUTRACK_COMPANY_NAME`, `MANUTRACK_PALETTE`, `MANUTRACK_ACCENT`, `APP_LOCALE`, `APP_TIMEZONE`), poi il predefinito.
- Schermata **Impostazioni › Azienda**: nome, logo dell'azienda (affiancato al marchio ManuTrack), lingua, fuso orario, palette (le tre dell'anteprima) oppure un colore accento personalizzato. Il contrasto viene verificato al salvataggio: se è insufficiente, l'app propone una tonalità vicina che rispetta AA.
- Il manifest della PWA è generato dinamicamente (nome breve, colori). Le icone 192, 512, maskable e apple-touch vengono rigenerate in PHP disegnando i poligoni della M con GD. Così un cambio di colore aggiorna le icone senza Node sul server.

### Accesso e ruoli
- **Laravel Fortify** con schermate nostre: limitazione dei tentativi, reset password, verifica email, conferma password, **2FA TOTP** con codici di recupero (obbligatoria per gli amministratori se impostato).
- **spatie/laravel-permission** senza la funzione *teams*. Una persona ha un account per installazione.

| Ruolo | Può |
|---|---|
| Amministratore | tutto: utenti e inviti, impostazioni azienda, aggiornamento, GDPR |
| Responsabile manutenzione | approvare segnalazioni, creare, assegnare e chiudere OdL, piani, costi, KPI |
| Tecnico | eseguire gli OdL assegnati, checklist, tempi, ricambi, foto, creare correttivi |
| Magazziniere | ricambi, movimenti, fornitori, prelievi per OdL |
| Richiedente (operatore) | segnalare guasti, vedere le proprie segnalazioni e lo stato delle macchine |
| Osservatore | sola lettura e dashboard |

Permessi granulari (es. `ordini.chiudere`). L'amministratore può ritoccare i ruoli predefiniti. Ruoli e permessi vengono riallineati in modo idempotente a ogni aggiornamento.

### Cicli di vita (invariati)

**Segnalazione**: `nuova` → `accettata` (genera un OdL) | `respinta` | `duplicata`.

**Ordine di lavoro**:
```
aperto ──► pianificato ──► in_corso ⇄ in_attesa (ricambi / fornitore / produzione)
   │            │              │
   └────────────┴──► annullato └──► completato ──► chiuso
                                        └── riaperto (torna in_corso)
```
Tipi: correttivo, preventivo, miglioramento. Priorità: bassa, media, alta, critica. Fermi registrati per MTTR e MTBF, codici guasto (problema, causa, rimedio), storico dei cambi di stato.

### File
Disco privato `storage/app/private` (predisposto per S3), download tramite controller con controllo dei permessi e URL firmati. **Nessun `storage:link`**: i collegamenti simbolici spesso non sono disponibili senza SSH. Foto: orientamento corretto, rimozione dei metadati GPS, varianti 400 e 1600 px con intervention/image (driver GD, presente su quasi tutti gli hosting; Imagick se c'è).

### Code, cron e hosting condiviso
- **Una sola voce di cron** in DirectAdmin: `* * * * * /usr/local/bin/php /home/UTENTE/domains/DOMINIO/manutrack/artisan schedule:run`.
- Lo Scheduler, ogni minuto e senza sovrapposizioni:
  - lancia `queue:work --stop-when-empty --max-time=50` (code su database: email, push, foto, esportazioni);
  - genera gli ordini preventivi, controlla scadenze e scorte, invia i riepiloghi, pulisce le iscrizioni push scadute;
  - se la versione del codice è più recente di quella del database, esegue `manutrack:update` (vedi sotto).
- In alternativa `QUEUE_CONNECTION=sync`, per installazioni minime senza cron (sconsigliato: le push rallentano le richieste).
- Nessun `exec` o `proc_open`: tutto avviene dentro PHP, perché sugli hosting condivisi queste funzioni sono spesso disabilitate.

### Installazione
- **`php artisan manutrack:install`** (se c'è SSH), interattivo o con opzioni per l'automazione (`--company=… --email=… --password=… --vapid --no-interaction`). Esegue in ordine:
  1. verifica dei requisiti (PHP 8.3, estensioni, permessi di scrittura, connessione al database);
  2. `APP_KEY` se manca;
  3. migrazioni;
  4. ruoli e permessi;
  5. impostazioni dell'azienda;
  6. amministratore;
  7. chiavi VAPID (scritte in `.env`) a richiesta;
  8. file di blocco `storage/app/installed.lock`.
- **Installazione guidata via web** (`/install`) per chi non ha SSH, con gli stessi passi. È attiva solo se l'app non è installata e richiede il codice `MANUTRACK_INSTALL_TOKEN` scritto in `.env`, così nessun estraneo può completare l'installazione al posto tuo. Dopo l'installazione si disattiva da sola.
- `php artisan manutrack:vapid` genera o rigenera le chiavi push anche in seguito. Rigenerarle invalida le iscrizioni esistenti e l'app lo avvisa.

### Rilasci e aggiornamenti
- `VERSION` (SemVer, es. `1.4.0`) e `CHANGELOG.md` (formato *Keep a Changelog*, in italiano).
- **`scripts/release.sh`** e **`scripts/release.ps1`** (Windows PowerShell), eseguiti sul tuo PC:
  1. copia pulita dal commit corrente (`git archive`);
  2. `composer install --no-dev --optimize-autoloader`;
  3. `npm ci && npm run build`;
  4. rimozione di `node_modules`, test, sorgenti frontend, `.env` e cache;
  5. creazione di `dist/manutrack-<versione>.zip` e del relativo hash SHA-256.
  Il pacchetto **non contiene** `.env` né file in `storage/`, quindi si può estrarre sopra un'installazione esistente senza toccare dati e configurazione.
- Caricamento: FTP oppure Gestione file di DirectAdmin (carica lo zip, poi "Estrai").
- **`php artisan manutrack:update`**:
  1. modalità manutenzione con pagina elegante;
  2. migrazioni `--force`;
  3. riallineamento idempotente di ruoli, permessi e impostazioni nuove;
  4. svuotamento e ricostruzione delle cache (config, route, view, eventi);
  5. riavvio dei worker;
  6. registrazione della versione nel database;
  7. uscita dalla manutenzione e stampa di versione precedente → nuova.
  Se rilanciato sulla stessa versione non fa danni ("già aggiornato").
- **Senza SSH** l'aggiornamento parte da solo al minuto successivo tramite il cron, che rileva la versione nuova. È disponibile anche il pulsante "Applica aggiornamento" in Impostazioni › Sistema, solo per gli amministratori. Una pagina di stato mostra versione, ultima esecuzione del cron, coda, chiavi push e requisiti.
- Le migrazioni sono scritte per essere ripetibili e compatibili sia con MySQL 8 sia con MariaDB: la CI le prova su entrambi, da zero e come aggiornamento dalla versione precedente.
- Facoltativo: `scripts/deploy-many` legge un elenco di installazioni (host FTP o SSH e cartella) e carica lo stesso pacchetto su tutte, con `lftp` o `rsync` se disponibili.

### Frontend, PWA e notifiche (invariati)
- Blade, Livewire 3, Alpine.js, Tailwind con i token del design system, Vite. La build avviene sul tuo PC; sul server arrivano solo i file compilati.
- Icone Lucide inline, font ospitati localmente, Chart.js solo nelle dashboard, Three.js caricato solo quando serve, con vista 2D di riserva.
- Scorciatoie da tastiera e palette comandi (`Ctrl/Cmd K`).
- PWA: manifest, icone, meta iOS, service worker (Workbox via `vite-plugin-pwa`), pagina offline, avviso "Nuova versione disponibile", coda delle segnalazioni offline in IndexedDB (Background Sync su Android, invio alla riapertura su iPhone).
- Web Push con minishlink/web-push:
  - più dispositivi per utente;
  - pulizia automatica delle iscrizioni scadute (404/410);
  - procedura guidata che arriva a "Attiva notifiche" solo dopo l'installazione e con un gesto dell'utente;
  - raggruppamento delle notifiche e badge sull'icona;
  - centro notifiche nell'app ed email come riserva;
  - notifica di prova e pagina diagnostica.

### Qualità
Pest 3 (test di funzionalità e Livewire, matrice ruoli×permessi, cicli di vita, installazione e aggiornamento idempotenti), Pint, Larastan, GitHub Actions su MySQL 8 e MariaDB. Screenshot desktop e telefono con Playwright a fine fase.

---

## 2. Modello dati

Nessuna colonna `tenant_id`. Tutte le tabelle hanno `created_at` e `updated_at`; dove serve, soft delete.

**Sistema e accesso**
- `settings`: chiave, valore (json)
- `system_versions`: versione installata, data e durata di ogni aggiornamento
- `users`: nome, email, password, lingua, mansione, attivo, campi 2FA, ultimo accesso
- `invitations`: email, ruolo, token (hash), scadenza, accettazione, invitato da
- tabelle spatie: `roles`, `permissions`, `model_has_roles`, `role_has_permissions`
- tabelle standard Laravel: `jobs`, `failed_jobs`, `job_batches`, `cache`, `sessions`, `password_reset_tokens`

**Impianti**
- `locations`: albero sito > area > linea (`type`, `parent_id`, `path`)
- `asset_categories`: nome, icona, forma 3D generica
- `assets`: codice (univoco), nome, categoria, ubicazione, macchina padre, matricola, costruttore, modello, anno, data di installazione, criticità A/B/C, stato (operativa, in guasto, in manutenzione, ferma, dismessa), `qr_token`, costo d'acquisto, garanzia, modello GLB, note
- `meters`, `meter_readings`
- `floor_plans`, `asset_placements` (x, z, rotazione, ingombro, altezza)
- `attachments` (polimorfica): disco, percorso, nome, MIME, dimensione, tipo, varianti

**Manutenzione**
- `work_requests`: numero, macchina, ubicazione, descrizione, macchina ferma sì/no, priorità suggerita, autore, stato, OdL generato, `client_uuid` (per le segnalazioni offline)
- `work_orders`: numero (`OdL-2026-00042`), tipo, titolo, descrizione, macchina, priorità, stato, responsabile, segnalazione e piano d'origine, scadenza, inizio pianificato, inizio e fine effettivi, chiusura, codici guasto, note di chiusura, costi (manodopera, ricambi, esterni, totale)
- `work_order_assignees`, `work_order_status_changes`, `work_order_comments`
- `checklist_templates`, `checklist_template_items`, `work_order_checklist_items`
- `time_entries`, `work_order_parts`, `work_order_costs`
- `downtime_events`, `failure_codes`
- `sequences`: numerazione progressiva per anno, con blocco di riga

**Preventiva**
- `maintenance_plans`: tipo (tempo o contatore), intervallo, modalità (data fissa o dall'ultima esecuzione), soglia del contatore, giorni di anticipo, prossima scadenza, priorità, assegnatario, durata stimata, checklist, attivo
- `maintenance_plan_parts`

**Magazzino**
- `parts`, `warehouses`, `stock_levels`, `stock_movements`, `suppliers`, `part_supplier`, `asset_part`

**Notifiche e conformità**
- `notifications` (database Laravel), `notification_preferences` (utente × evento × canale)
- `notification_rules` (email per ruolo ed evento)
- `push_subscriptions`: utente, endpoint (hash univoco), chiavi, dispositivo, piattaforma, ultimo uso, errori
- `audit_logs`: utente, azione, oggetto, modifiche prima e dopo, IP, user agent
- `data_exports`: archivio ZIP, scadenza

Eventi notificabili: nuovo ordine assegnato, cambio di stato, scadenza vicina, segnalazione critica, scorta minima ricambi, **manutenzione preventiva in scadenza**.

### GDPR in un'installazione dedicata
- **Esportazione completa**: archivio ZIP con JSON e CSV di ogni tabella, più i file allegati. Viene preparata in coda, scaricabile dall'amministratore tramite link a scadenza.
- **Diritto all'oblio per una persona**: anonimizzazione dell'utente. Lo storico degli interventi resta, ma senza nome, email e dati personali.
- **Cancellazione di tutti i dati** a fine contratto: `php artisan manutrack:purge` oppure la stessa funzione nelle impostazioni, con doppia conferma e codice. Svuota il database e i file, lasciando l'app pronta per una nuova installazione.

### Demo
Installazione separata con `MANUTRACK_DEMO=true`:
- dati fittizi (azienda, persone e macchine inventate);
- banner "Ambiente dimostrativo" e credenziali dei ruoli demo mostrate nella pagina di accesso;
- azzeramento automatico ogni notte alle 3:00 tramite lo Scheduler (ricrea il database e ricarica i dati).
L'azzeramento è bloccato se `MANUTRACK_DEMO` non è attivo.

---

## 3. Direzione visiva

Invariata. **Palette: Grafite e Rame**, salvo tua diversa indicazione (nel messaggio la scelta era rimasta tra parentesi). Le altre due restano disponibili come preimpostazioni nelle impostazioni dell'azienda.

---

## 4. Piano a fasi

Ogni fase si chiude con:
- test automatici verdi, Pint e Larastan puliti;
- aggiornamento di `VERSION` e `CHANGELOG.md` e un commit chiaro;
- pacchetto di rilascio di prova;
- screenshot desktop e telefono;
- aggiornamento di `docs/SPECIFICA.md`.

| # | Fase | Contenuto principale |
|---|---|---|
| 1 | Fondamenta, design system, installazione e rilascio | Laravel 12, Pest, Pint, Larastan, CI (MySQL e MariaDB), `.env.example`, docker-compose facoltativo per lo sviluppo, Tailwind con i token, logo SVG e componenti Blade, layout PC e telefono, traduzioni `lang/it`, `docs/DESIGN.md`. Poi `manutrack:install` e installazione web, verifica requisiti, `manutrack:update`, `VERSION`, `CHANGELOG.md`, `scripts/release.sh` e `.ps1`, prima stesura di `docs/INSTALLAZIONE.md` (DirectAdmin) |
| 2 | Accesso, ruoli, impostazioni | Fortify con 2FA, utenti e inviti, ruoli e permessi con test per ruolo, impostazioni azienda (nome, logo, lingua, colori e contrasto), icone generate in PHP, manifest dinamico, base del registro attività, pagina Sistema (versione, cron, coda) |
| 3 | **Prova PWA e push** | app installabile (manifest, icone, meta iOS, service worker minimo, pagina offline), chiavi VAPID, iscrizione per dispositivo, pulsante "Invia notifica di prova", pagina diagnostica, procedura guidata di installazione per iPhone e Android. **Guida al test su dispositivi reali** e ad HTTPS, compreso come provare in locale con un tunnel HTTPS |
| 4 | Macchine e impianti | ubicazioni, categorie, schede macchina, allegati protetti, foto ridimensionate, QR stampabili, contatori, import CSV |
| 5 | Segnalazioni e ordini di lavoro | segnalazione rapida e da QR, ciclo di vita, checklist, tempi, commenti, foto, fermi, codici guasto, elenchi con filtri e scorciatoie, storico macchina |
| 6 | Magazzino | ricambi, magazzini, movimenti, fornitori, distinta per macchina, prelievo su OdL, scorta minima |
| 7 | Manutenzione preventiva | piani a tempo e a contatore, generazione automatica, calendario, rispetto dei piani |
| 8 | Notifiche complete e offline | eventi e preferenze, email per ruolo, raggruppamento, badge, centro notifiche, avviso nuova versione, coda offline delle segnalazioni |
| 9 | Dashboard e report | KPI, filtri per periodo e sito, grafici animati, esportazioni CSV/XLSX |
| 10 | 3D | M 3D d'ingresso, gemello digitale con editor e vista 2D di riserva, anteprima 3D della macchina |
| 11 | GDPR, demo e consegna | esportazione, anonimizzazione, cancellazione totale, installazione demo con azzeramento notturno, revisione di sicurezza, README, `docs/INSTALLAZIONE.md` completa, licenze delle librerie, verifica finale PC, iPhone e Android, `scripts/deploy-many` facoltativo |

### `docs/INSTALLAZIONE.md` (indice previsto)
1. Requisiti dell'hosting: PHP 8.3 con le estensioni `pdo_mysql`, `mbstring`, `openssl`, `curl`, `gd`, `zip`, `intl`, `fileinfo`, `bcmath` o `gmp`; MySQL 8 o MariaDB 10.6+.
2. Dominio o sottodominio in DirectAdmin.
3. Database e utente MySQL (MySQL Management).
4. HTTPS con Let's Encrypt (SSL Certificates), con reindirizzamento forzato su HTTPS.
5. Caricamento ed estrazione del pacchetto nella cartella `manutrack/`.
6. Cartella pubblica, tre metodi:
   - (a) `public_html` sostituita da un collegamento a `manutrack/public`, creato anche senza SSH con una voce di cron da eseguire una sola volta;
   - (b) cartella del sottodominio puntata su `public`;
   - (c) `.htaccess` di riserva che inoltra a `public/` e blocca tutto il resto.
7. `.env`: database, URL, posta, `MANUTRACK_INSTALL_TOKEN`.
8. Cron di Laravel: una voce, con il percorso di PHP 8.3 corretto (es. `/usr/local/php83/bin/php` se ci sono più versioni).
9. Installazione via web o `manutrack:install`, e chiavi push.
10. Verifica dalla pagina Sistema e prova della notifica.
11. Aggiornamenti: carica il pacchetto, estrai, attendi un minuto (o premi "Applica aggiornamento").
12. Risoluzione dei problemi frequenti.
