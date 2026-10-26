# ManuTrack: proposta di progetto

Stato: **in attesa di approvazione**. Nessun codice applicativo è stato scritto.
Anteprima dello stile: `docs/anteprima/stile.html` (da aprire nel browser).

---

## 1. Architettura

**Monolite modulare Laravel 12** (PHP 8.3, MySQL 8 / MariaDB 10.6+). Un solo deploy, organizzato per moduli di dominio:

| Modulo | Contenuto |
|---|---|
| `Tenancy` | aziende, appartenenze, inviti, contesto azienda, global scope |
| `Platform` | area amministrazione piattaforma (aziende clienti, sospensione, uso) |
| `Assets` | ubicazioni, categorie, macchine, contatori, QR, allegati, layout 3D |
| `Maintenance` | segnalazioni, ordini di lavoro, checklist, tempi, fermi, codici guasto |
| `Preventive` | piani a tempo e a contatore, generazione automatica, calendario |
| `Inventory` | ricambi, magazzini, giacenze, movimenti, fornitori |
| `Notifications` | preferenze, push, email per ruolo, centro notifiche, diagnostica |
| `Reporting` | KPI, storico macchina, esportazioni CSV/XLSX |
| `Compliance` | registro attività, esportazione e cancellazione dati (GDPR) |

Struttura: `app/Models`, `app/Livewire/<Modulo>`, `app/Actions/<Modulo>` (logica di business in classi azione testabili, riusate da Livewire, API JSON e comandi), `app/Policies`, `app/Enums` (stati, priorità, tipi come enum PHP con etichetta, colore e icona), `app/Notifications`, `app/Jobs`.

### Multi-azienda (un database, colonna `tenant_id`)
- Trait `BelongsToTenant` su **ogni** modello di dati: aggiunge il global scope `TenantScope` e imposta `tenant_id` in creazione.
- `TenantContext` (singleton) impostato da un middleware dall'azienda corrente dell'utente. **Chiusura in sicurezza**: senza contesto, lo scope restituisce zero righe (non tutte). L'area piattaforma usa solo `withoutTenancy()` esplicito e registrato nell'audit.
- I job in coda serializzano il `tenant_id` e ripristinano il contesto (job middleware); lo Scheduler itera azienda per azienda.
- Indici univoci composti (`tenant_id`, `codice`); validazione `exists` sempre filtrata per azienda, così non si può collegare un ID di un'altra azienda.
- Un test "architetturale" verifica che ogni modello con `tenant_id` usi il trait; una suite di isolamento crea dati in due aziende e prova lettura, modifica, binding delle rotte (404), file, esportazioni, notifiche e ricerca.
- Un utente può appartenere a più aziende (es. tecnico esterno) con ruoli diversi; si cambia azienda dal menu profilo.

### Autenticazione e ruoli
- **Laravel Fortify** (senza interfaccia: le schermate le disegniamo noi in Blade/Livewire): login con limitazione tentativi, reset password, verifica email, conferma password, **2FA TOTP** con codici di recupero (obbligatoria per gli amministratori se l'azienda lo imposta).
- **spatie/laravel-permission** con la funzione *teams* (`team_id` = `tenant_id`): ruoli e permessi per azienda.

| Ruolo | Può |
|---|---|
| Amministratore azienda | tutto, utenti e inviti, impostazioni, logo, GDPR |
| Responsabile manutenzione | approvare segnalazioni, creare/assegnare/chiudere OdL, piani, costi, KPI |
| Tecnico | eseguire gli OdL assegnati, checklist, tempi, ricambi, foto, creare correttivi |
| Magazziniere | ricambi, movimenti, fornitori, prelievi per OdL |
| Richiedente (operatore) | segnalare guasti, vedere le proprie segnalazioni e lo stato macchine |
| Osservatore | sola lettura e dashboard (es. direzione) |
| *Admin piattaforma* | area separata `/piattaforma`: aziende, piani, sospensione. Nessun accesso ai dati delle aziende salvo "accesso di supporto" autorizzato dall'azienda, a tempo e registrato |

I permessi sono granulari (es. `ordini.chiudere`, `ricambi.movimentare`); l'amministratore azienda può ritoccare i ruoli predefiniti.

### Cicli di vita

**Segnalazione** (richiesta di intervento): `nuova` → `accettata` (genera un OdL) | `respinta` | `duplicata` (collegata a un'altra).

**Ordine di lavoro (OdL)**:
```
aperto ──► pianificato ──► in_corso ⇄ in_attesa (ricambi / fornitore / produzione)
   │            │              │
   └────────────┴──► annullato └──► completato ──► chiuso
                                        └── riaperto (torna in_corso)
```
- Il tecnico porta l'ordine a `completato` (checklist obbligatorie compilate, note, tempi); il responsabile verifica e passa a `chiuso`, che congela i costi.
- Ogni cambio di stato è registrato con autore, ora e motivo.
- Tipi: correttivo, preventivo, miglioramento (più "ispezione" come variante del preventivo). Priorità: bassa, media, alta, critica.
- Fermi macchina: se la macchina è ferma, l'OdL apre un evento di fermo che si chiude al rientro in servizio. Da qui MTTR, MTBF e durata dei fermi.
- Codici guasto (problema / causa / rimedio) per analisi ricorrenze.

### File
Disco privato (`storage/app/private`, oppure S3 cambiando `FILESYSTEM_DISK`), percorsi `tenants/{id}/...`, download solo tramite controller con policy e URL firmati a scadenza. Foto: correzione orientamento EXIF, rimozione metadati GPS, varianti 400 e 1600 px in WebP/JPEG con intervention/image (in coda). Controllo MIME reale, limiti di dimensione, GLB fino a 20 MB.

### Frontend
- Blade + Livewire 3 + Alpine.js, Tailwind CSS con token del design system, Vite.
- Icone Lucide (SVG inline, funzionano offline). Font ospitati localmente (@fontsource).
- Grafici: Chart.js (leggero, animato) caricato solo nelle dashboard.
- Scorciatoie: palette comandi `Ctrl/Cmd K`, sequenze `G O`, `G M`…, `N` nuovo, `/` cerca, `?` elenco scorciatoie.
- **Three.js** solo via `import()` dinamico nelle pagine 3D. Prima di caricarlo si controllano WebGL2, memoria del dispositivo, `saveData` e `prefers-reduced-motion`; altrimenti si mostra la vista 2D isometrica (SVG). Nessuna azione dipende dal 3D.
- La M 3D della pagina di ingresso è la geometria del logo estrusa (`ExtrudeGeometry`), quindi è fedele al marchio.

### PWA e notifiche
- Manifest completo, icone 192/512 normali e maskable, apple-touch-icon 180, meta iOS, splash per iOS generati dal logo con uno script.
- Service worker con Workbox (via `vite-plugin-pwa`, modalità injectManifest): file statici in cache, pagina offline, aggiornamento controllato con avviso "Nuova versione disponibile".
- **Segnalazioni offline**: il modulo di segnalazione invia a un endpoint JSON (non Livewire), con identificativo univoco generato sul telefono per evitare doppioni. Senza rete la richiesta, foto incluse, va in IndexedDB; si invia con Background Sync su Android e all'apertura o al ritorno della rete su iPhone (Safari non ha Background Sync).
- Web Push con minishlink/web-push, chiavi VAPID da `php artisan manutrack:vapid`, più iscrizioni per utente (una per dispositivo), rimozione automatica alle risposte 404/410.
- Procedura guidata al primo accesso: rileva iPhone, Android o PC, mostra i passaggi animati per installare e propone "Attiva notifiche" solo dopo, con un gesto dell'utente.
- Raggruppamento: tag per oggetto (lo stesso OdL sostituisce la notifica precedente) e riepilogo se arrivano più di N eventi in pochi minuti. Badge sull'icona (Badging API), centro notifiche interno, email come riserva, pulsante di prova e pagina diagnostica.

### Code, pianificazioni, esportazioni
- Code su driver `database`. Un solo cron: `* * * * * php artisan schedule:run`. Lo Scheduler genera i preventivi, controlla scadenze e scorte, invia i riepiloghi, pulisce le iscrizioni push e, sugli hosting senza processo worker, lancia `queue:work --stop-when-empty`.
- Esportazioni CSV/XLSX in streaming con **openspout** (più leggero di maatwebsite/excel).
- QR: generati lato server in SVG (chillerlan/php-qrcode); contengono un link `https://<dominio>/m/<token>`, quindi funzionano anche con la fotocamera nativa dell'iPhone. Scansione in app con `qr-scanner` (usa BarcodeDetector dove esiste, altrimenti un worker).

### Qualità
Pest 3 (test di funzionalità, Livewire, isolamento, matrice ruoli×permessi, cicli di vita), Pint, Larastan livello 6+, GitHub Actions con MySQL 8. Screenshot automatici desktop e telefono con Playwright a fine fase.

---

## 2. Modello dati

Tutte le tabelle di dati hanno `tenant_id` (indicizzato, chiave esterna), `created_at` e `updated_at`; dove serve, soft delete.

**Piattaforma e accesso (senza `tenant_id`)**
- `tenants`: nome, slug, ragione sociale, P. IVA, logo, colore accento personalizzato, lingua, fuso orario, impostazioni (json), stato (attiva, sospesa, in cancellazione), demo, consenso all'accesso di supporto
- `users`: nome, email, password, lingua, `current_tenant_id`, `is_platform_admin`, campi 2FA, ultimo accesso
- `tenant_user`: appartenenza, mansione, stato · `invitations`: email, ruolo, token (hash), scadenza, accettazione
- tabelle spatie: `roles`, `permissions`, `model_has_roles`… con `team_id`

**Impianti**
- `locations`: albero sito > area > linea (`type`, `parent_id`, `path`)
- `asset_categories`: nome, icona, forma 3D generica (pressa, tornio, nastro, robot, compressore, quadro, generica)
- `assets`: codice, nome, categoria, ubicazione, macchina padre (componenti), matricola, costruttore, modello, anno, data installazione, criticità (A/B/C), stato (operativa, in guasto, in manutenzione, ferma, dismessa), `qr_token`, costo d'acquisto, garanzia, modello GLB, note
- `meters`, `meter_readings`: contatori ore e cicli e relative letture
- `floor_plans` (dimensioni, griglia, sfondo per area o linea) e `asset_placements` (x, z, rotazione, ingombro, altezza)
- `attachments` (polimorfica): disco, percorso, nome, MIME, dimensione, tipo (foto, documento, modello 3D), varianti

**Manutenzione**
- `work_requests`: numero, macchina, ubicazione, descrizione, macchina ferma sì/no, priorità suggerita, autore, stato, OdL generato, `client_uuid` (per le segnalazioni offline)
- `work_orders`: numero (`OdL-2026-00042`), tipo, titolo, descrizione, macchina, priorità, stato, responsabile, segnalazione e piano d'origine, scadenza, inizio pianificato, inizio/fine effettivi, chiusura, codici guasto, note di chiusura, costi di manodopera, ricambi, esterni e totale
- `work_order_assignees` (più tecnici), `work_order_status_changes`, `work_order_comments`
- `checklist_templates`, `checklist_template_items` e `work_order_checklist_items` (spunta, valore numerico con limiti, testo, OK/KO, foto, obbligatorio)
- `time_entries` (tecnico, inizio, fine, minuti, costo orario al momento)
- `work_order_parts` (previsto e usato, costo unitario al momento), `work_order_costs` (servizi esterni, fornitore)
- `downtime_events` (macchina, OdL, inizio, fine, minuti, motivo, pianificato sì/no)
- `failure_codes` (problema, causa, rimedio)
- `sequences` (numerazione progressiva per azienda e anno, con blocco di riga)

**Preventiva**
- `maintenance_plans`: codice, nome, macchina, tipo (tempo o contatore), intervallo e unità, modalità (data fissa o dall'ultima esecuzione), contatore e soglia, giorni di anticipo, prossima scadenza, priorità, assegnatario predefinito, durata stimata, modello di checklist, attivo
- `maintenance_plan_parts` (ricambi previsti)

**Magazzino**
- `parts`: codice, nome, unità, codice costruttore, scorta minima, quantità di riordino, costo medio ponderato, fornitore preferito, foto
- `warehouses`, `stock_levels` (giacenza, impegnato, ubicazione a scaffale)
- `stock_movements`: carico, scarico su OdL, rettifica, trasferimento, reso; quantità, costo, utente, OdL, fornitore
- `suppliers`, `part_supplier` (prezzo, tempi di consegna, codice fornitore), `asset_part` (distinta ricambi per macchina)

**Notifiche e conformità**
- `notifications` (database Laravel, più `tenant_id`), `notification_preferences` (utente × azienda × evento × canale)
- `tenant_notification_rules` (email per ruolo ed evento, decise dall'amministratore)
- `push_subscriptions`: utente, endpoint (con hash univoco), chiavi, dispositivo, piattaforma, ultimo uso, errori
- `audit_logs`: utente, azione, oggetto, modifiche prima e dopo, IP, user agent
- `data_exports` (archivio ZIP GDPR, scadenza) e `tenant_deletions` (richiesta, periodo di ripensamento, esito)

---

## 3. Direzione visiva (in sintesi)

- **Grafite e Rame** (consigliata): fondi grafite con una punta di blu, accento rame. Alternative nell'anteprima: Acciaio e Ciano, Ardesia e Indaco.
- Il colore forte è riservato agli **stati**, secondo la filosofia delle interfacce HMI ad alte prestazioni e i colori andon: verde operativa, rosso guasto, blu manutenzione, grigio ferma, giallo scadenza e attesa. Sono sempre accompagnati da icona e testo e non cambiano con la palette.
- Caratteri: Archivo espanso per i titoli, IBM Plex Sans per il testo, IBM Plex Mono per codici e numeri (tutti con licenza OFL).
- Logo: M ricalcata dal file originale in SVG, con colori da `--logo-faccia-chiara`, `--logo-faccia-scura`, `--logo-ombra`, `--logo-testo`; varianti completa, solo M, orizzontale, mono chiara e scura. Nell'app la scritta sarà convertita in tracciati, quindi non dipende dai font installati.

---

## 4. Piano a fasi

Ogni fase si chiude con: test automatici verdi, Pint e Larastan puliti, un commit chiaro, screenshot desktop e telefono, aggiornamento di `docs/SPECIFICA.md`.

| # | Fase | Contenuto principale |
|---|---|---|
| 1 | Fondamenta e design system | Laravel 12, Pest, Pint, Larastan, CI, Docker opzionale, `.env.example`, Tailwind con token, logo SVG e componenti Blade, generazione di favicon, icone e splash, layout (barra laterale PC, barra in basso telefono, aree sicure, dvh), manifest e meta iOS, traduzioni `lang/it`, `docs/DESIGN.md` |
| 2 | Aziende, accesso e ruoli | Fortify con 2FA, aziende, inviti, ruoli e permessi, `BelongsToTenant` con la suite di isolamento, area piattaforma, impostazioni azienda (nome, logo, colore), base dell'audit log |
| 3 | Macchine e impianti | albero ubicazioni, categorie, schede macchina, allegati protetti con foto ridimensionate, QR stampabili (etichette singole e fogli A4), contatori, import CSV |
| 4 | Segnalazioni e ordini di lavoro | segnalazione rapida da telefono e da QR, ciclo di vita completo, checklist, tempi, commenti, foto, fermi, codici guasto, elenchi con filtri e scorciatoie, storico macchina |
| 5 | Magazzino | ricambi, magazzini, movimenti, fornitori, distinta per macchina, prelievo su OdL, scorta minima |
| 6 | Manutenzione preventiva | piani a tempo e a contatore, generazione automatica, calendario, rispetto dei piani |
| 7 | PWA completa e notifiche | service worker, pagina offline, avviso aggiornamento, coda offline, VAPID, iscrizioni, procedura guidata, preferenze, email per ruolo, centro notifiche, badge, notifica di prova, diagnostica |
| 8 | Dashboard e report | MTTR, MTBF, fermi, arretrato, preventivo/correttivo, costi, rispetto piani, filtri per periodo e sito, esportazioni CSV/XLSX |
| 9 | 3D | M 3D nella pagina di ingresso, gemello digitale con editor e vista di riserva 2D, anteprima 3D in scheda macchina (GLB o forma per categoria), inclinazione delle schede |
| 10 | GDPR, demo e consegna | esportazione e cancellazione dei dati di un'azienda, modalità demo con reset notturno, revisione di sicurezza, README, guida di installazione (HTTPS, VAPID, cron), elenco librerie e licenze, verifica finale PC/iPhone/Android |

---

## 5. Domande aperte

1. **Palette**: confermi Grafite e Rame, o preferisci una delle alternative?
2. **"Preventivo in scadenza"** tra gli eventi da notificare: l'ho interpretato come *manutenzione preventiva in scadenza* (piano che sta per scadere o già scaduto). Se intendevi un *preventivo economico* di un fornitore, aggiungo la gestione delle offerte nel modulo fornitori.
3. **Utenti su più aziende**: va bene che lo stesso account possa appartenere a più aziende (es. manutentore esterno)?
4. **Demo**: una demo pubblica con accesso a un clic e reset ogni notte va bene, oppure solo su richiesta?
