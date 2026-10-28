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
