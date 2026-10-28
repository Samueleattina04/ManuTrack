# Esito del controllo di sicurezza

- **Data:** 28 ottobre 2026
- **Versione controllata:** 0.12.0 (commit `227093c`, ramo `claude/friendly-ride-0g7qop`); correzioni nella **0.12.1** (ramo `claude/controllo-sicurezza`)
- **Piano seguito:** [CONTROLLO-SICUREZZA.md](CONTROLLO-SICUREZZA.md)
- **Ambiente:** PHP 8.3, MariaDB 10.11, Chromium con Playwright; solo i database `manutrack` (sviluppo, dati dimostrativi) e `manutrack_test`. Nessuna installazione reale toccata.

Ogni problema ha un test in `tests/Feature/Security/` (o lo script Playwright
`scripts/e2e/csp-check.cjs`). Per dimostrare che i test colgono i problemi, gli stessi test sono
stati eseguiti anche sul codice della 0.12.0: **48 falliscono prima delle correzioni**, 26
passano (sono le verifiche di comportamenti già corretti, elencate più sotto). Dopo le
correzioni passano tutti.

## 1. Problemi trovati

| # | Gravità | Problema | Stato |
|---|---|---|---|
| P1 | alta | Link per la nuova password avvelenabile con `X-Forwarded-Host` | corretto |
| P2 | alta | `X-Forwarded-For` accettato da chiunque: limiti per IP aggirabili, IP falsi nel registro | corretto |
| P3 | alta (se il permesso è delegato) | `users.manage` dato a un altro ruolo permette di diventare amministratore | corretto |
| P4 | alta | Persona disattivata con la pagina aperta continua a lavorare | corretto |
| P5 | media | Password riconfermata non richiesta alle azioni Livewire dopo la scadenza | corretto |
| P6 | media | Cambiare la password non chiude le altre sessioni né i "ricordami" | corretto |
| P7 | media | Iscrizioni push verso qualunque indirizzo `https` (SSRF) | corretto |
| P8 | media | Azioni senza limite di richieste (email, esportazioni, password) | corretto |
| P9 | media | Nessuna CSP né intestazioni di sicurezza sulle pagine | corretto |
| P10 | media | `.htaccess` sostituiva le intestazioni dell'app su Apache | corretto |
| P11 | bassa | Enumerazione degli account da "password dimenticata" e dai tempi dell'accesso | corretto |
| P12 | bassa | `/design` raggiungibile in produzione da chiunque abbia fatto l'accesso | corretto |
| P13 | bassa | `migrate:fresh` / `db:wipe` eseguibili in produzione | corretto |
| P14 | bassa | Indirizzi `/storage/…` del disco privato (serve: true) attivi senza motivo | corretto |
| P15 | bassa | Temporanei di Livewire abbandonati mai cancellati senza nuovi caricamenti | corretto |
| P16 | bassa | `manutrack:install` riparte sopra un'installazione cancellata | corretto |
| P17 | bassa | Snapshot Livewire alterato: errore 500 con traccia nei registri a ogni tentativo | corretto |
| P18 | bassa | Tentativi sbagliati di cancellazione totale non registrati | corretto |
| P19 | bassa | `SESSION_ENCRYPT=false` in `.env.example` | corretto (nuove installazioni) |
| A1 | bassa | `'unsafe-eval'` nella CSP | accettato (motivo sotto) |
| A2 | bassa | Documenti: tipo deciso dall'estensione | accettato |
| A3 | bassa | Tempo di risposta di "password dimenticata" (invio della mail) | accettato |
| A4 | bassa | Id inesistente dello ZIP: 404 invece di 403 | accettato |
| A5 | bassa | Pacchetto di aggiornamento senza firma | accettato |
| A6 | info | Larastan livello 8 su `app/Http` e `app/Livewire` | deciso: resta il livello 6 |
| A7 | bassa | Limite di accesso solo per email + IP (nessun blocco dell'account) | accettato |

### P1 — Link per la nuova password avvelenabile (alta)

- **Dove:** `bootstrap/app.php:28` (`trustProxies(at: '*')`), nessun `URL::forceRootUrl`.
- **Scenario:** l'attaccante chiede "password dimenticata" per l'email dell'amministratore con
  `X-Forwarded-Host: attaccante.example.net`. L'amministratore riceve una mail vera, dal suo
  sistema, con il link `http://attaccante.example.net/reset-password/<token>`: un clic e il
  token arriva all'attaccante, che si prende l'account. Verificato: il link conteneva l'host
  dell'attaccante.
- **Correzione:** `AppServiceProvider` forza la radice degli indirizzi su `APP_URL` (e `https`
  se lo è); i proxy fidati sono ristretti (P2).
- **Test:** `HostAndProxyTest` (link per la nuova password, anche da proxy locale; invito).

### P2 — Proxy fidati "*" (alta)

- **Dove:** `bootstrap/app.php:28`.
- **Scenario:** se Apache è raggiungibile direttamente (DirectAdmin senza Nginx davanti, il caso
  più comune), ogni tentativo di accesso con un `X-Forwarded-For` diverso conta come un altro
  indirizzo: i limiti di 5/30 tentativi al minuto non valgono più e il registro attività
  riporta IP inventati.
- **Correzione:** `app/Http/Middleware/TrustProxies.php`: di serie fidati solo loopback, reti
  private e l'indirizzo del server stesso; `MANUTRACK_TRUSTED_PROXIES` per un proxy esterno.
- **Test:** `HostAndProxyTest` (6 tentativi con 6 IP finti → il sesto è bloccato; da proxy
  locale o configurato l'IP vero passa).

### P3 — Delega di `users.manage` (alta, solo se il permesso è dato a un altro ruolo)

- **Dove:** `app/Livewire/Users/Index.php` (`invite`, `changeRole`, `toggleActive`,
  `resetTwoFactor`, `sendPasswordReset`, `resend`), `app/Livewire/Users/Roles.php` (`toggle`,
  `restoreDefaults`), `app/Actions/Users/ManageUser.php`, `InviteUser.php`.
- **Scenario:** l'amministratore dà `users.manage` al responsabile perché inviti i tecnici. Il
  responsabile cambia il proprio ruolo in amministratore, oppure invita un proprio indirizzo
  come amministratore, oppure si dà `system.update` e `data.export` dalla matrice dei permessi.
  Poteva anche disattivare l'amministratore o azzerargli la verifica in due passaggi.
- **Correzione:** chi non è amministratore non assegna né invita il ruolo di amministratore,
  non tocca gli account degli amministratori, non cambia il proprio ruolo; la matrice la vede
  ma la cambia solo l'amministratore.
- **Test:** `AccessDelegationTest` (due test).

### P4 — Persone disattivate con Livewire già caricato (alta)

- **Dove:** `EnsureUserIsActive` non era tra i middleware persistenti di Livewire, quindi
  `/livewire/update` non lo eseguiva.
- **Scenario:** un tecnico licenziato viene disattivato mentre ha aperto un ordine: può
  continuare a commentare, cambiare stati, prelevare ricambi finché la pagina resta aperta.
  Verificato con una chiamata diretta a `/livewire/update`.
- **Correzione:** `Livewire::addPersistentMiddleware([EnsureUserIsActive::class, …])` in
  `AppServiceProvider`. La sessione si chiude alla prima azione.
- **Test:** `AccessDelegationTest` ("una persona disattivata con la pagina già aperta…").

### P5 — Password riconfermata solo all'apertura (media)

- **Dove:** `password.confirm` non era persistente in Livewire; e se lo fosse stato, con
  `Accept: application/json` il middleware di Laravel risponde 423 invece di reindirizzare, e
  Livewire esegue comunque l'azione (blocca solo i reindirizzamenti).
- **Scenario:** l'amministratore lascia aperta la pagina di anonimizzazione o di esportazione;
  dopo le 3 ore di validità della conferma chi usa quel PC (o una sessione rubata) anonimizza,
  esporta o cambia la conservazione senza conoscere la password.
- **Correzione:** `app/Http/Middleware/RequirePassword.php` (alias `password.confirm`): per le
  richieste Livewire reindirizza sempre alla conferma e poi riporta alla pagina; aggiunto ai
  middleware persistenti.
- **Test:** `GdprForgedRequestsTest` ("l'amministratore senza password riconfermata…", anche
  con `Accept: application/json`).

### P6 — Sessioni dopo il cambio della password (media)

- **Scenario:** una persona si accorge che qualcuno usa il suo account e cambia la password; la
  sessione dell'altro (o il suo "ricordami") resta valida.
- **Correzione:** `EndSessionsAfterPasswordChange` (impronta HMAC della password nella
  sessione, legata all'id della persona) e nuovo `remember_token` al cambio dal profilo. Vale
  anche per il link di reimpostazione e per l'anonimizzazione.
- **Test:** `AccessDelegationTest` ("cambiando la password si chiudono le altre sessioni…").

### P7 — SSRF con le iscrizioni push (media)

- **Dove:** `PushSubscriptionController` accettava ogni URL `https`.
- **Scenario:** una persona qualunque registra come dispositivo `https://127.0.0.1:…/` o un
  servizio della rete dell'hosting; a ogni avviso (o con "Notifica di prova", 10 al minuto) il
  server ci manda una richiesta POST.
- **Correzione:** solo i servizi push dei browser (`MANUTRACK_PUSH_HOSTS`, di serie Google,
  Mozilla, Apple, Microsoft), niente IP, porte o credenziali nell'indirizzo; anche nel rinnovo
  dal service worker.
- **Test:** `PushEndpointTest`.

### P8 — Limitazione delle richieste (media) — vedi §2.1

### P9 — CSP e intestazioni (media) — vedi §2.2

### P10 — `.htaccess` e intestazioni dell'app (media)

- **Dove:** `public/.htaccess` usava `Header always set`.
- **Scenario:** verificato con Apache 2.4 e mod_php: le pagine arrivavano con due
  `X-Frame-Options` (`SAMEORIGIN` e `DENY`), due `Referrer-Policy` e due `Permissions-Policy`,
  una con `camera=(self)` su tutte le pagine.
- **Correzione:** `Header setifempty` con gli stessi valori dell'app (vale per i file statici).
- **Test:** `SecurityHeadersTest` (ultimo test) e prova manuale su Apache.

### P11–P19 (basse)

- **P11 Enumerazione:** "password dimenticata" diceva "Non esiste un utente con questo
  indirizzo" e "Attendi" solo per chi esiste; l'accesso senza account non calcolava l'hash
  (risposta più veloce). Ora risposta identica e hash fittizio (`FortifyServiceProvider`).
  Test: `RateLimitTest` (password dimenticata, accesso).
- **P12 `/design`:** guida di stile visibile a tutti in produzione; ora 404 e fuori dal menu.
  Test: `InstallUpdateDemoTest`.
- **P13 Comandi distruttivi:** `DB::prohibitDestructiveCommands()` in produzione; l'azzeramento
  della demo lo toglie solo dopo i suoi tre controlli. Test: `InstallUpdateDemoTest`.
- **P14 `/storage/{path}`:** il disco `local` aveva `serve: true` (rotte GET e PUT con firma),
  mai usate; ora `serve: false`. Test: `TemporaryUploadsTest`, `PlantTest` aggiornato
  (404 invece di 403).
- **P15 Temporanei abbandonati:** Livewire pulisce solo al caricamento successivo; ora anche
  la pulizia notturna (stessa voce di cron). Test: `TemporaryUploadsTest`.
- **P16 Reinstallazione dopo la cancellazione:** togliendo il file di blocco, `manutrack:install`
  ricreava un database mentre le pagine restavano "cancellate". Ora si rifiuta finché c'è il
  rapporto. Test: `InstallUpdateDemoTest`.
- **P17 Snapshot alterati:** l'eccezione `CorruptComponentPayloadException` dava 500 e una
  traccia nei registri a ogni richiesta (riempibili a piacere); ora 419 senza registrazione.
- **P18 Registro:** i tentativi di cancellazione totale con frase o password sbagliata ora
  lasciano `system.erase_refused` (solo quale controllo è fallito).
- **P19 Sessioni cifrate:** `.env.example` propone `SESSION_ENCRYPT=true` (le installazioni
  esistenti non cambiano: cambiandolo chi è collegato deve rientrare).

### Accettati e da decidere

- **A1 `'unsafe-eval'`:** necessario a Livewire 3 (Alpine standard). La build CSP di Alpine
  richiederebbe di riscrivere quasi tutti i template e Livewire 3 non la include. Il rischio
  è contenuto: nessuno script senza nonce può partire. Da rivedere con Livewire 4.
- **A2 Documenti:** il tipo si sceglie dall'estensione (elenco ammesso), ma il tipo vero è
  rilevato dal contenuto e un file che non è immagine o PDF si scarica sempre come
  `application/octet-stream` con `nosniff`: un HTML chiamato `.pdf` non si apre mai nel browser
  (test). Le foto passano dal decodificatore. I PDF inline non hanno `sandbox` (Chrome non li
  mostrerebbe).
- **A3 Tempi di "password dimenticata":** la mail è inviata subito, quindi per un account
  esistente la risposta è un po' più lenta. Mettere la mail in coda la ritarderebbe fino a un
  minuto (cron); i limiti per IP rendono la misura lenta.
- **A4 Id dello ZIP:** a chi non ha il permesso un id inesistente risponde 404 e uno esistente
  403: si scopre solo quante esportazioni ci sono state.
- **A5 Aggiornamenti senza firma:** il pacchetto non si scarica da internet, lo carica chi
  gestisce l'hosting; chi può scrivere i file può già eseguire codice. `release.json` serve a
  verificare che il caricamento sia completo (dimensioni), e l'edizione deve coincidere col
  database (test esistenti in `UpdateTest` e `DemoEditionTest`).
- **A6 Larastan livello 8** su `app/Http` e `app/Livewire`: 188 avvisi, quasi tutti
  `auth()->user()` che potrebbe essere nullo su rotte già protette da `auth` (nessun problema
  di sicurezza trovato). Si resta a livello 6; per salire servirebbe un aiuto tipizzato per la
  persona collegata. **Decisione del titolare (28 ottobre 2026): resta il livello 6.**
- **A7 Accesso:** nessun blocco dell'account dopo N errori (permetterebbe a chiunque di
  bloccare un collega); restano 5 tentativi al minuto per email + IP e 30 per IP, ora con l'IP
  vero (P2). La verifica in due passaggi è consigliata agli amministratori.

### Decisioni del titolare

28 ottobre 2026: esito approvato. A1, A2, A3, A4, A5 e A7 accettati come descritti; A6: resta
il livello 6 di Larastan. Aggiunte nella stessa 0.12.1: verifica in due passaggi obbligatoria per
gli amministratori (impostazione dell'azienda), controllo di `APP_URL` in installazione e nella
pagina Sistema, email di prova dalla pagina Sistema, errori comprensibili per le iscrizioni push
rifiutate, checklist dopo l'installazione in `INSTALLAZIONE.md` §12.

## 2. I sei punti obbligatori

### 2.1 Limitazione delle richieste

Stato di partenza verificato: limiti su accesso, due passaggi, installazione, pagine
dell'invito, segnalazioni, push. Nessun limite su password dimenticata (solo uno al minuto per
persona di Laravel), uso del link, conferma della password, password nel profilo e nella
cancellazione totale, inviti inviati, link mandati dall'amministratore, esportazioni CSV/Excel,
rapporto mensile, esportazione completa (bloccata solo se ce n'era una in corso), caricamento
dei modelli 3D. Il limite dei caricamenti temporanei (`throttle:60,1`) era già per persona.

Aggiunti i limiti della tabella in [SICUREZZA.md](SICUREZZA.md) §3: rotte di Fortify con
`ThrottleAuthForms`, rotte HTTP con `throttle:exports` e `throttle:uploads`, azioni Livewire
con `App\Support\Security\ActionLimit` (contatore per chiave, messaggio con l'attesa). Il link
mandato dall'amministratore ora dice "attendi" invece di "inviato" quando Laravel non lo manda.

Test (`RateLimitTest`, 15 test): per ognuno il tentativo oltre il limite riceve 429 o il
messaggio previsto, nulla viene inviato o salvato, e un'altra persona o un altro IP non è
bloccato (la chiave è quella giusta). Segnalazioni: 30 al minuto per persona; la coda offline
che arriva a raffica riceve 429 per l'eccedenza e la tiene in coda per il tentativo successivo
(`outbox.js` tratta 429 come temporaneo).

### 2.2 CSP e intestazioni su tutte le pagine

Stato di partenza: CSP solo sui file serviti (allegati, modelli, logo); nelle pagine nessuna
CSP; `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` e HSTS solo da
`.htaccess` (quindi non su Nginx), con la fotocamera permessa ovunque.

Ora `SecurityHeaders` (middleware globale) su ogni risposta, CSP con nonce per richiesta sulle
pagine HTML; politica ed eccezioni con i motivi in [SICUREZZA.md](SICUREZZA.md) §2. Tolti i
dieci gestori `onclick=`/`onchange=`/`onsubmit=` dalle viste (Alpine nelle pagine Livewire, un
piccolo script con nonce nelle pagine autonome). Eccezione scoperta con Playwright e non
prevista dal piano: Three.js legge le texture dei GLB con `fetch()` da `blob:` e `data:`, quindi
`connect-src 'self' data: blob:` (nessuna uscita in rete).

Verifiche:
- `SecurityHeadersTest` (8 test): ogni pagina GET dell'amministratore (oltre 40), pagine per
  ospiti, 403, 404, installazione, rapporto di cancellazione (410): CSP con nonce, ogni
  script e stile in linea con il nonce, nessun gestore in linea, intestazioni presenti; nonce
  diverso a ogni richiesta; fotocamera solo in Segnala; HSTS solo in HTTPS e disattivabile;
  modalità report/off; file serviti con la loro CSP; `.htaccess` senza sostituzioni.
- `scripts/e2e/csp-check.cjs` con Chromium, **prima in Report-Only poi applicata**: 53 tipi di
  pagina, Livewire e Alpine, palette comandi, grafici, pianta 3D (WebGL) e 2D, anteprima 3D,
  modello GLB con texture incorporate, fotocamera per il QR, anteprima delle foto (`blob:`),
  service worker, fotocamera negata fuori da Segnala, cancellazione totale dentro un iframe di
  un altro sito (bloccata da `frame-ancestors`): **nessuna violazione**.
- Anche gli script esistenti (`assets`, `inventory`, `preventive`, `report`, `pwa`, `plant`,
  `three-lifecycle`) passano con la CSP applicata. In `pwa-check` resta non verificabile qui
  solo l'iscrizione al servizio push di Google (rete non raggiungibile dall'ambiente).

### 2.3 Caricamenti temporanei di Livewire

Verificato (`TemporaryUploadsTest`, 9 test): senza firma, con firma scaduta o alterata il
caricamento è rifiutato (401); la dimensione massima vale già per il temporaneo (422) e di
nuovo al salvataggio; il nome del file temporaneo è generato dal server (il nome del browser,
anche `../../../.env.jpg`, non entra nel percorso); un riferimento falsificato o firmato per un
altro file è rifiutato (403); l'anteprima richiede la firma e vale solo per PNG, JPEG e WebP
(rilevati dal contenuto); il disco è privato (`storage/app/private/livewire-tmp`), senza
indirizzi pubblici (P14); il contenuto decide il tipo (un HTML chiamato `.jpg` non diventa foto,
uno chiamato `.pdf` si scarica soltanto); un modello GLB caricato come temporaneo non diventa
logo né foto, un SVG non diventa logo; chi non può allegare non salva il temporaneo di un altro;
dopo il salvataggio il temporaneo si cancella, quelli abbandonati spariscono entro un giorno
(P15); la cancellazione totale li toglie. Il riferimento firmato non è legato alla persona: è
accettabile perché è casuale, valido solo per quel file e chi lo usa deve comunque avere il
permesso sul componente in cui lo salva (test).

### 2.4 Installazione, aggiornamento e demo in produzione

Verificato (`InstallUpdateDemoTest`, 8 test, più i test esistenti in `tests/Feature/Setup`):
dopo l'installazione `/install` risponde 404 e non si riattiva cancellando il file di blocco
(test esistente) né mandando un codice vuoto; due installazioni contemporanee: la seconda trova
il blocco e non installa nulla; dopo la cancellazione totale l'installazione risponde 410 e
non riparte né dal browser né dal terminale (P16); "Aggiorna" vuole l'amministratore con la
password riconfermata; pacchetto incompleto o di un'edizione diversa rifiutato (test
esistenti); la demo non parte e non azzera un cliente nemmeno con `MANUTRACK_DEMO=true`, il
pacchetto demo copiato sopra o il divieto dei comandi distruttivi tolto a mano; i seeder in
produzione non creano nulla; `migrate:fresh`, `migrate:reset` e `db:wipe` non partono in
produzione (P13); nessuna rotta di sviluppo o debug (Ignition, Telescope, Debugbar…) e `/design`
404 in produzione (P12); con `APP_DEBUG=false` gli errori non mostrano dettagli.

### 2.5 Dipendenze e segreti nello storico

- `composer audit`: nessun avviso. `npm audit --omit=dev` e `npm audit` (con gli strumenti di
  sviluppo): 0 vulnerabilità. Aggiunti alla CI (lavoro "Stile e analisi statica").
- Storico git: un solo ramo remoto (`claude/friendly-ride-0g7qop`), 21 commit. **gitleaks
  8.21** su tutto lo storico (`--log-opts=--all`): nessun segreto. Ricerca a mano su
  `git log -p --all` (APP_KEY, VAPID, password di database e posta, AWS, chiavi private, token
  GitHub e simili) e sui nomi dei file (`.env`, `.pem`, `.sql`, `auth.json`, `dist/`): solo la
  chiave `APP_KEY` fittizia dei test in `phpunit.xml` (usata solo dai test, la CI ne genera
  un'altra) e segnaposto della documentazione. **Nessun segreto da ruotare.**
- File attuali e `public/build`: gitleaks pulito. Artefatto della CI (pacchetti cliente e demo
  0.12.0): nessun `.env`, `auth.json`, database o factory; l'unico avviso di gitleaks è un
  falso positivo (la stringa `-----BEGIN EC PRIVATE KEY-----` nel codice di
  `web-token/jwt-library`).

### 2.6 Cancellazione, esportazione e anonimizzazione con richieste costruite a mano

`GdprForgedRequestsTest` (13 test). Per responsabile, tecnico, magazziniere, richiedente,
osservatore, responsabile con `settings.manage`, e per il responsabile con `data.export`:
pagine HTTP (403), download dello ZIP con id vero o indovinato (403/404), chiamate dirette a
`/livewire/update` con gli snapshot presi dalle pagine dell'amministratore (`erase`,
`anonymize`, `start`, `delete`, `save` con `understood`, `phrase`, `password`, `confirm`,
`userId` impostati) e con snapshot modificati a mano: tutto rifiutato (403 o 419), e database,
file, esportazioni e impostazioni restano identici. Il responsabile con `data.export` esporta
(permesso dato apposta, come da `docs/GDPR.md`) ma non cancella né anonimizza.
L'amministratore senza password riconfermata viene mandato alla conferma, anche chiedendo JSON
(P5). Le stesse chiamate con la password riconfermata funzionano (la prova non è vuota). Il job
`ExportCompanyData` con un id arbitrario o di un'esportazione già pronta non fa nulla.
Il tentativo di cancellazione con la password sbagliata resta nel registro (P18).

Sul codice della 0.12.0 i controlli dei permessi per i non amministratori reggevano già: quei
test fallivano solo per il 500 sugli snapshot alterati (P17). Il buco reale era la password
riconfermata (P5).

## 3. Verificato senza problemi

- **Matrice ruolo × azione e IDOR:** ogni metodo Livewire che riceve un id lo cerca dentro
  l'oggetto della pagina (allegati, contatori, checklist, tempi, fermi, giacenze, fornitori,
  giorni del calendario, voci della pianta) o tra gli oggetti della persona (avvisi, dispositivi
  push); allegati e modelli serviti con lo stesso controllo della scheda; il richiedente vede
  solo le proprie segnalazioni; le esportazioni mostrano i costi solo a chi può vederli.
- **XSS:** nessun `x-html`/`innerHTML`; i `{!! !!}` riguardano SVG generati dal server (icone,
  QR), CSS con soli colori esadecimali convalidati, testi tradotti con valori già escapati.
- **SQL:** le espressioni grezze sono costanti; ordinamenti da elenchi fissi; la ricerca
  dell'anonimizzazione usa parametri legati.
- **CSV/XLSX:** ogni testo passa da `SafeCell` (test esistenti).
- **Inviti:** solo impronta del codice, scadenza, uso singolo, indirizzo già registrato
  rifiutato (test esistenti).
- **Esportazione completa:** niente password, codici, token, sessioni, chiavi (test esistente);
  ZIP con nomi ripuliti, scadenza effettiva.
- **Service worker:** pagine sempre dalla rete tranne Segnala, svuotata all'uscita
  (`Clear-Site-Data`); niente cache di Livewire, allegati, accesso.
- **Cookie:** `HttpOnly`, `SameSite=Lax`, `Secure` con `SESSION_SECURE_COOKIE=true`;
  rigenerazione dell'id all'accesso (Fortify).
- **Copie di sicurezza:** fuori da `public/` e con `.htaccess` di blocco (test esistente).

## 4. Fuori dal controllo

- **Hosting e server:** configurazione di Apache, Nginx, LiteSpeed e PHP (`disable_functions`,
  `open_basedir`, permessi dei file), isolamento tra gli account del pannello, copie fatte
  dall'hosting, certificati. Indicazioni per Nginx in [SICUREZZA.md](SICUREZZA.md) §6.
- **Posta:** SPF, DKIM, DMARC del dominio, sicurezza del server SMTP e della casella.
- **Servizi push** dei browser e rete dell'hosting (la prova reale dell'iscrizione push non è
  stata possibile da questo ambiente).
- **Dispositivi** delle persone (telefoni condivisi, browser vecchi senza CSP3).
- **Dischi S3:** con `FILESYSTEM_DISK=s3` i file e i temporanei restano nel bucket anche dopo
  la cancellazione totale: vanno tolti dal pannello del fornitore.

## 5. Esito delle verifiche finali

- Suite completa (`php artisan test`, MariaDB 10.11): **548 test superati** (4462 verifiche), di cui 74 nuovi in `tests/Feature/Security`; prima del controllo erano 474.
- `vendor/bin/pint --test`: nessuna modifica. `vendor/bin/phpstan analyse` (livello 6): nessun
  errore.
