# Piano di controllo di sicurezza (da far eseguire a una sessione nuova)

Scopo: un controllo completo di ManuTrack prima della 1.0, fatto da chi non ha scritto il codice,
con **prove riproducibili** (test automatici o script) per ogni problema trovato.

## Regole per chi esegue il controllo

- Lavorare su un ramo dedicato; **non** toccare installazioni reali, solo il database di sviluppo
  (`manutrack`) e quello dei test (`manutrack_test`).
- Per ogni problema: gravità (critica, alta, media, bassa), file e riga, scenario concreto di
  attacco, **un test Pest che fallisce prima della correzione e passa dopo**, correzione minima.
- Non indebolire controlli esistenti per far passare i test; non saltare, disattivare o mettere
  in quarantena test.
- I dati di prova restano fittizi (`example.com`, nomi inventati). Nessuna credenziale nel
  repository.
- Alla fine: elenco dei problemi con stato (corretto / accettato con motivo / da decidere),
  suite completa, Pint, Larastan, CI verde, voce nel CHANGELOG.

## 0. Punti obbligatori (richiesti esplicitamente)

Questi sei punti vanno coperti tutti, ciascuno con test automatici e con un paragrafo dedicato
nell'esito, anche quando non emergono problemi. Lo "stato di partenza" descrive il codice alla
0.12.0 ed è un punto da cui partire, non una conclusione: va verificato.

### 0.1 Limitazione delle richieste (rate limiting)

Verificare con test che superato il limite si riceva 429 (o l'errore previsto) e che il limite
sia per la chiave giusta (IP, persona, email): accesso, verifica in due passaggi, inviti
(accettazione e invio), reimpostazione della password (richiesta del link anche da parte
dell'amministratore e uso del link), esportazioni (complete ed elenchi CSV/XLSX), caricamenti
(foto, documenti, logo, modelli GLB, import, caricamenti temporanei di Livewire), invio delle
segnalazioni (anche offline in coda, che arrivano a raffica al ritorno della rete).

Stato di partenza: limiti in `app/Providers/FortifyServiceProvider.php` (login, two-factor) e
`AppServiceProvider.php` (install, invitation, reports, push, push-test); `throttle:60,1` sui
caricamenti temporanei in `config/livewire.php`. Le azioni Livewire (es. "Prepara
l'esportazione", "Invia link per nuova password") passano tutte da `/livewire/update` e **non**
hanno un limite proprio: l'esportazione completa si blocca solo se ce n'è già una in corso.
Valutare limiti per azione e la protezione dall'invio ripetuto di email.

### 0.2 CSP e intestazioni di sicurezza su tutte le pagine

Proporre e applicare (con un middleware globale e test) una politica per **tutte** le risposte
HTML, oltre a quelle già presenti sulle rotte dei file:
`Content-Security-Policy` (con `frame-ancestors 'none'`), `X-Content-Type-Options: nosniff`,
`Referrer-Policy`, `Permissions-Policy` (fotocamera solo nelle pagine del QR, nessun
microfono/geolocalizzazione), `Strict-Transport-Security` configurabile, `Cross-Origin-Opener-Policy`.

Valutare e **documentare ogni eccezione** con il motivo, in `docs/` e nell'esito:
- Livewire 3 e Alpine nella build standard valutano espressioni con `new Function`, quindi
  richiedono `'unsafe-eval'`, a meno di passare alla build CSP di Alpine (con i suoi limiti
  sulle espressioni nei template): confrontare le due strade;
- script e stili inline (Livewire inserisce script e stili; tema e palette impostati prima del
  caricamento della pagina): nonce per richiesta (`Vite::useCspNonce`, `@livewireScripts`
  con nonce) invece di `'unsafe-inline'`;
- Three.js e il caricatore GLB: WebGL, immagini e buffer come `blob:` e `data:`, eventuali
  worker; il visualizzatore blocca già gli URL esterni, la CSP deve farlo anche lei
  (`connect-src 'self'`, `img-src 'self' data: blob:`);
- Chart.js, jsQR (fotocamera, canvas), service worker (`worker-src 'self'`), manifest, icone,
  notifiche push (`connect-src` verso i servizi push non serve dal browser: verificarlo).
Provare ogni pagina con Playwright a CSP attiva (in modalità `Report-Only` prima, poi
applicata) e fallire il test se compaiono violazioni in console.

### 0.3 Caricamenti temporanei di Livewire

Verificare: tipi accettati e controllati sul contenuto (non sul nome), dimensione massima
(`MANUTRACK_UPLOAD_MAX_MB`) prima e dopo il caricamento, firma e scadenza dell'URL di
caricamento, che un file temporaneo non sia leggibile da un'altra persona né da un URL
pubblico (anteprime `preview_mimes`, disco e cartella `livewire-tmp`), pulizia automatica
(`cleanup`) anche su hosting senza cron dedicato, nomi dei file e path traversal, file
temporanei rimasti dopo la cancellazione totale, uso di un temporaneo caricato in un
componente per salvarlo in un altro (es. un modello GLB come foto).

### 0.4 Installazione, aggiornamento e demo non raggiungibili in produzione

Per ogni rotta e comando (`/install`, `manutrack:install`, `manutrack:update` e il pulsante
"Aggiorna", `manutrack:demo`, azzeramento notturno, `migrate:fresh`/seeder):
- dopo l'installazione l'installer risponde 404 e non si riattiva cancellando il file di
  blocco, svuotando il codice in `.env`, con due richieste contemporanee o con un database
  vuoto (es. dopo la cancellazione totale);
- l'aggiornamento è riservato all'amministratore con password riconfermata e non accetta un
  pacchetto di un'edizione diversa o manomesso;
- la demo non parte e non azzera mai un'installazione cliente, anche con `MANUTRACK_DEMO=true`
  o con il pacchetto demo copiato sopra; i seeder non fanno nulla in produzione;
- nessuna rotta di sviluppo (`/design`, debug, Telescope o simili) raggiungibile con
  `APP_ENV=production`.

### 0.5 Dipendenze e segreti nello storico

- `composer audit` e `npm audit --omit=dev` (e anche con le dipendenze di sviluppo, per la
  CI): ogni avviso con decisione (aggiornare, accettare con motivo); valutare di aggiungerli
  alla CI.
- Storico git completo (`git log -p --all`, tutti i rami): nessuna chiave (`APP_KEY`, VAPID,
  password del database o della posta, token) mai presente, anche in commit poi corretti.
  Usare uno strumento dedicato (gitleaks o trufflehog) oltre alla ricerca a mano; controllare
  anche i pacchetti in `dist/` e gli artefatti della CI. Se emerge un segreto: segnalarlo
  subito al titolare, non riscrivere la storia senza il suo consenso, indicare cosa ruotare.

### 0.6 Cancellazione totale, esportazione e anonimizzazione con richieste costruite a mano

Per ogni ruolo diverso dall'amministratore (e per un responsabile a cui sono stati dati
`data.export` o `settings.manage`), e per un amministratore **senza** password riconfermata:
- rotte HTTP (`/sistema/dati/...`, download dello ZIP con id indovinato o di un altro);
- chiamate dirette a `/livewire/update` con lo snapshot di un componente preso da un'altra
  pagina o modificato a mano (metodi `erase`, `anonymize`, `start`, `delete`, `save`;
  proprietà `understood`, `phrase`, `confirm`, `userId` alterate);
- comandi e code (`ExportCompanyData` con un id arbitrario);
- verificare che nulla cambi nel database e su disco e che il tentativo resti nel registro
  quando ha senso.

## 1. Inventario della superficie d'attacco (prima di cercare problemi)

1. Elencare tutte le rotte (`php artisan route:list --json`) con middleware e permesso; segnalare
   ogni rotta senza `auth`, senza `can:` o senza controllo nel componente Livewire.
2. Elencare ogni componente Livewire e, per ciascuno, **ogni metodo pubblico** e **ogni proprietà
   pubblica**: le proprietà sono modificabili dal browser, i metodi chiamabili con qualunque
   argomento.
3. Elencare comandi artisan, job in coda, pianificazioni (`routes/console.php`), rotte senza
   sessione (service worker, manifest, file, push).

## 2. Autorizzazione (la priorità più alta)

- **Matrice ruolo × azione**: per ognuno dei 6 ruoli e per ogni rotta e ogni metodo Livewire,
  verificare con un test che chi non ha il permesso riceva 403 (o errore di validazione) e che
  nulla cambi nel database. Esiste già una matrice in `tests/Feature/Access`: controllarne la
  completezza rispetto all'inventario del punto 1.
- **IDOR**: per ogni id passato dal browser (ordini, segnalazioni, allegati, macchine, piani,
  ricambi, magazzini, esportazioni, calendari, voci della pianta, avvisi, iscrizioni push)
  verificare che non si possa agire su un oggetto non visibile al proprio ruolo (es. il
  richiedente che vede solo le proprie segnalazioni, gli avvisi di un'altra persona,
  l'esportazione richiesta da un altro amministratore).
- **Proprietà Livewire manomesse**: provare a impostare da test valori fuori dall'interfaccia
  (`->set('userId', …)`, ruoli non offerti, stati non consentiti, `confirmPast`, id di altri
  oggetti) e chiamare metodi con argomenti arbitrari.
- **Azioni riservate all'amministratore**: correggi stato, data passata nell'orario di lavoro,
  anonimizzazione, cancellazione totale, esportazione completa, aggiornamenti, ripristino;
  verificare anche con un responsabile a cui è stato dato `settings.manage` o `data.export`.
- **Persone**: ultimo amministratore, auto-disattivazione, account disattivati che perdono subito
  l'accesso (sessioni già aperte, "ricordami", Livewire già caricato), account anonimizzati non
  riattivabili né reimpostabili.

## 3. Autenticazione e sessione

- Limite dei tentativi di accesso, della verifica in due passaggi, del link di reimpostazione
  password, degli inviti; enumerazione degli utenti (messaggi e tempi di risposta diversi).
- Inviti: solo impronta del codice nel database, scadenza, uso singolo, invito per email già
  esistente.
- Verifica in due passaggi: codici di recupero, riuso del codice, azzeramento da parte
  dell'amministratore.
- Cookie: `Secure`, `HttpOnly`, `SameSite`; rigenerazione dell'id dopo l'accesso; durata
  (`SESSION_LIFETIME`); valutare `SESSION_ENCRYPT=true` (oggi false in `.env.example`).
- Conferma della password (`password.confirm`) sulle pagine sensibili: verificare dove manca.

## 4. Input, output e file

- **XSS**: cercare `{!! !!}`, `x-html`, `innerHTML`, `@js`/`@json` in attributi, testi del
  registro attività e degli avvisi (titoli con nomi di macchine o testi utente), etichette della
  pianta 3D e 2D (SVG generato dal server), rapporti stampabili, email, notifiche push,
  `INDICE.md` dell'esportazione.
- **SQL**: cercare `whereRaw`, `orderByRaw`, `DB::raw`, `selectRaw` con valori dall'utente
  (anche ordinamenti e filtri degli elenchi, `PersonAnonymizer::matching`).
- **Esportazioni CSV/XLSX**: protezione dalle formule (CSV injection) su ogni colonna di testo.
- **Caricamenti**: foto (dimensioni, decompression bomb, tipi falsi, HEIC), documenti, logo (SVG
  rifiutato), modelli GLB (dimensione massima, intestazione, riferimenti esterni, JSON enorme),
  import CSV/XLSX (righe enormi, formule, file zip bomb). Nomi dei file nei percorsi e nelle
  intestazioni `Content-Disposition`.
- **Servizio dei file**: allegati, modelli, logo ed esportazioni solo con permesso; tipo di
  contenuto corretto, `nosniff`, CSP `sandbox`, nessun percorso pubblico.
- **SSRF**: ogni richiesta in uscita (push verso i servizi dei browser: controllare l'elenco degli
  host ammessi in `PushSubscription`, eventuali URL in impostazioni o file).
- **ZIP**: path traversal nei nomi dentro l'esportazione e nel pacchetto di aggiornamento.

## 5. Intestazioni e browser

- Oggi le CSP sono solo sulle rotte dei file: valutare **intestazioni di sicurezza globali**
  (CSP compatibile con Livewire, Alpine e Vite; `X-Frame-Options`/`frame-ancestors`,
  `Referrer-Policy`, `Permissions-Policy` con fotocamera solo dove serve il QR, HSTS
  configurabile).
- Service worker: cosa mette in cache (mai pagine con dati personali dopo l'uscita), ambito,
  aggiornamento; IndexedDB della segnalazione offline svuotato all'uscita.
- Clickjacking sulle azioni distruttive (cancellazione totale, anonimizzazione).

## 6. Installazione, aggiornamenti e server

- Installazione dal browser: codice da `.env`, una sola volta, poi 404; corsa tra due richieste.
- Aggiornamento automatico: integrità del pacchetto (`release.json`, impronte), cosa succede con
  un file manomesso, blocco su file, permessi delle cartelle; nessun `exec`/`proc_open`.
- Copie di sicurezza e ripristino: file `.sql.gz` manomessi, percorsi, cartella protetta dal web
  (`.htaccess`, ma anche Nginx/LiteSpeed: documentare).
- Modalità demo: impossibile attivarla su un'installazione cliente; azzeramento notturno solo se
  pacchetto, `.env` e database dicono tutti "demo".
- `APP_DEBUG=false` e pagine d'errore senza dettagli in produzione; nessun segreto nei log.

## 7. Dati personali (fase 11)

- Esportazione: nessun segreto (password, 2FA, token, chiavi VAPID, password della posta) in
  nessun file dello ZIP; ZIP scaricabile solo da chi ha il permesso; scadenza effettiva.
- Anonimizzazione: provare a ritrovare la persona dopo l'operazione (ricerca globale, registro,
  JSON, nomi dei file, avvisi, cache, sessioni); concorrenza con una sessione ancora aperta.
- Cancellazione totale: frase, password, corsa con altre richieste, cosa resta su disco.

## 8. Dipendenze e configurazione

- `composer audit` e `npm audit --omit=dev`; versioni di Laravel, Livewire, Fortify, openspout,
  Three.js, Chart.js, jsQR; licenze già in `docs/LIBRERIE.md`.
- `.env.example` senza valori reali; `config/` senza segreti; storico git senza credenziali
  (cercare chiavi, password, token in tutti i commit).

## 9. Strumenti consigliati

- Test Pest (feature) per ogni scenario; Larastan già a livello 6, valutare il livello 8 sulle
  cartelle `app/Http` e `app/Livewire`.
- Playwright per gli scenari nel browser (cache del service worker, CSP, clickjacking).
- Revisione manuale guidata dall'inventario del punto 1, non solo strumenti automatici.

## Risultato atteso

Un rapporto `docs/CONTROLLO-SICUREZZA-ESITO.md` con: data, versione controllata, elenco dei
problemi (gravità, scenario, test, correzione o decisione), cosa è stato verificato senza
problemi, cosa resta fuori dal controllo (hosting, server, posta).
