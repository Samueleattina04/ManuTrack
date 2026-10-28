# Sicurezza: intestazioni, limiti e configurazione

Cosa fa ManuTrack per proteggere le pagine e i dati, e cosa resta da configurare sul server.
L'esito del controllo di sicurezza della 0.12.1 è in [CONTROLLO-SICUREZZA-ESITO.md](CONTROLLO-SICUREZZA-ESITO.md).

## 1. Intestazioni su tutte le risposte

Le aggiunge `app/Http/Middleware/SecurityHeaders.php` a ogni risposta, comprese le pagine
d'errore, l'installazione e il rapporto della cancellazione totale.

| Intestazione | Valore | Note |
|---|---|---|
| `Content-Security-Policy` | vedi §2 | solo sulle pagine HTML; i file serviti (allegati, modelli 3D, logo) hanno la loro CSP più stretta |
| `X-Content-Type-Options` | `nosniff` | |
| `Referrer-Policy` | `same-origin` | nessun indirizzo interno verso altri siti |
| `X-Frame-Options` | `DENY` | per i browser vecchi; i nuovi usano `frame-ancestors 'none'` |
| `Cross-Origin-Opener-Policy` | `same-origin` | |
| `Permissions-Policy` | `camera=()`, `microphone=()`, `geolocation=()`, … | fotocamera `camera=(self)` **solo** in *Segnala* (lettura del QR) |
| `Strict-Transport-Security` | `max-age=31536000` | solo in HTTPS; `MANUTRACK_HSTS_SECONDS=0` la toglie |

`public/.htaccess` aggiunge le stesse intestazioni con `Header setifempty`, quindi solo dove
l'app non le ha già messe (i file statici di `public/`). Con `Header always set` Apache
sostituirebbe quelle dell'app (per esempio la fotocamera di *Segnala*): non usarlo.

## 2. Content-Security-Policy

```
default-src 'self';
script-src 'self' 'nonce-<casuale>' 'unsafe-eval';
style-src 'self' 'nonce-<casuale>'; style-src-attr 'unsafe-inline';
img-src 'self' data: blob:; font-src 'self' data:;
connect-src 'self' data: blob:; media-src 'self' blob:;
worker-src 'self'; manifest-src 'self';
frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'
```

Il nonce cambia a ogni richiesta (`Vite::useCspNonce()`): lo ricevono i file di Vite, la
configurazione di Livewire (`@livewireStyles`, `@livewireScriptConfig`) e i pochi script e stili
in linea (tema prima del disegno, palette dell'azienda, pagine autonome). Nessuna vista usa
gestori in linea (`onclick=` e simili): un test lo controlla.

### Eccezioni e motivi

| Eccezione | Perché | Alternativa valutata |
|---|---|---|
| `'unsafe-eval'` negli script | Livewire 3 contiene Alpine nella build standard, che valuta le espressioni dei template (`wire:click`, `x-data`, `@click`) con `new Function`. | La build CSP di Alpine non valuta codice ma accetta solo nomi di proprietà e metodi (niente `$event.target.value`, `!open`, chiamate con argomenti): andrebbero riscritti quasi tutti i template e Livewire 3 non la include (arriva con Livewire 4). Il rischio resta basso perché nessuno script esterno o in linea senza nonce può partire: `'unsafe-eval'` serve solo a chi riesce già a far eseguire codice. Da rivedere con Livewire 4. |
| `style-src-attr 'unsafe-inline'` | Attributi `style=""` nei template e quelli che Alpine e Livewire impostano durante gli aggiornamenti. Non eseguono codice. | Gli elementi `<style>` richiedono comunque il nonce. |
| `img-src data: blob:` | Anteprime delle foto prima del caricamento (`blob:`), icone `data:` nei CSS, immagini dentro i modelli GLB. | |
| `connect-src data: blob:` | Three.js legge le immagini dentro i modelli GLB con `fetch()` da `blob:` (texture nel file binario) o da `data:` (texture incorporate nel JSON). | Nessuno dei due esce dal browser: verso la rete resta solo `'self'`, quindi un modello che rimanda a un sito esterno non scarica nulla (lo blocca anche il visualizzatore). |
| `media-src blob:` | Flusso della fotocamera per il QR. | |
| `font-src data:` | Caratteri incorporati nel CSS compilato. | |

Le notifiche push non richiedono `connect-src` verso i servizi push: l'iscrizione la fa il
browser, non la pagina (verificato con Playwright).

### Modalità

`MANUTRACK_CSP=enforce` (predefinito) la applica; `report` la manda come
`Content-Security-Policy-Report-Only` (le violazioni compaiono solo nella console del browser,
utile per provare una modifica); `off` la toglie (sconsigliato). Le altre intestazioni restano.

### Prova nel browser

```bash
php artisan migrate:fresh --seed && php artisan serve
LOGIN_EMAIL=admin@example.com LOGIN_PASSWORD=manutrack-demo node scripts/e2e/csp-check.cjs
```

Visita tutte le pagine raggiungibili dall'amministratore e prova Livewire, Alpine, grafici, 3D
(pianta, anteprima e un modello GLB con texture), fotocamera, anteprima delle foto, service
worker e il caricamento in un iframe di un altro sito; fallisce alla prima violazione, sia con
la CSP applicata sia in Report-Only. **Attenzione**: carica un modello 3D su una macchina
(`UPLOAD_GLB=0` per saltarlo); usarlo solo su un'installazione di prova.

## 3. Limiti delle richieste

| Cosa | Limite | Chiave |
|---|---|---|
| Accesso | 5 al minuto; 30 al minuto | email + IP; IP |
| Verifica in due passaggi | 5 al minuto | persona |
| Pagina dell'invito (apertura e accettazione) | 10 al minuto | IP |
| Inviti inviati (e rinnovati) | 20 l'ora; 3 l'ora | chi invita; indirizzo email |
| Link per la nuova password (password dimenticata) | 5 al minuto; 1 al minuto | IP; persona (Laravel) |
| Link mandato dall'amministratore | 10 ogni 10 minuti; 1 al minuto | amministratore; persona |
| Uso del link per la nuova password | 10 al minuto | IP |
| Conferma della password | 5 al minuto | persona + IP |
| Password attuale (profilo, verifica in due passaggi, cancellazione totale) | 5 al minuto | persona |
| Esportazioni CSV/Excel e rapporto mensile | 10 al minuto | persona |
| Esportazione completa | una alla volta, 3 l'ora | installazione |
| Caricamenti (temporanei di Livewire, modelli 3D) | 60 al minuto | persona (IP prima dell'accesso) |
| Segnalazioni | 30 al minuto | persona; la coda offline del telefono riprova dopo un 429 |
| Notifiche push (iscrizioni, prova) | 30 e 10 al minuto | persona |
| Installazione dal browser | 5 al minuto | IP |

"Password dimenticata" risponde sempre allo stesso modo, anche per un indirizzo che non esiste,
e l'accesso calcola un hash anche senza account: né il messaggio né il tempo di risposta dicono
chi ha un account.

## 4. Proxy e indirizzi

- **Proxy fidati.** Di serie ManuTrack si fida di `X-Forwarded-For`/`-Proto` solo se la
  richiesta arriva da questo server o da una rete privata (Nginx davanti ad Apache nello stesso
  server, come in DirectAdmin). Chi arriva da internet non può fingere un altro indirizzo IP
  (aggirando i limiti per IP e falsando il registro). Con un proxy esterno (bilanciatore, CDN)
  elencarne gli indirizzi in `MANUTRACK_TRUSTED_PROXIES` (separati da virgole, CIDR ammessi).
- **Indirizzi nelle email.** I link (nuova password, inviti, avvisi) usano sempre `APP_URL`,
  mai l'host della richiesta: `APP_URL` deve essere l'indirizzo pubblico in `https://`.
- **Notifiche push.** Il server invia solo ai servizi push dei browser
  (`MANUTRACK_PUSH_HOSTS`, di serie Google, Mozilla, Apple e Microsoft): un'iscrizione verso un
  altro indirizzo è rifiutata.

### Servizi push ammessi

Il server manda le notifiche solo agli indirizzi di questi servizi (o loro sottodomini), elenco
in `MANUTRACK_PUSH_HOSTS` (separati da virgole); qualunque altra iscrizione è rifiutata e la
diagnostica notifiche (*App e notifiche › Diagnostica*) lo spiega con il servizio del browser.

| Host ammesso | Servizio | Browser |
|---|---|---|
| `fcm.googleapis.com` | Google Firebase Cloud Messaging | Chrome, Edge su Android, Samsung Internet, Opera, Brave |
| `android.googleapis.com` | Google (indirizzi vecchi di FCM) | iscrizioni Chrome molto vecchie |
| `push.services.mozilla.com` | Mozilla Push Service (es. `updates.push.services.mozilla.com`) | Firefox |
| `push.apple.com` | Apple Push Notification service (es. `web.push.apple.com`) | Safari su iPhone, iPad e Mac |
| `notify.windows.com` | Microsoft Windows Push Notification Services (es. `wns2-par02p.notify.windows.com`) | Edge su Windows |

Solo `https`, senza porte, credenziali o indirizzi IP nell'indirizzo.

## 5. Sessioni

- Cambiando la password (dal profilo o con il link) le altre sessioni della persona si chiudono
  alla richiesta successiva e i "ricordami" degli altri dispositivi non valgono più.
- Una persona disattivata perde subito l'accesso, anche con una pagina già aperta: le azioni
  Livewire ripassano dal controllo (`EnsureUserIsActive` tra i middleware persistenti).
- Le pagine con la password riconfermata (Sistema, Dati e privacy) la richiedono anche per le
  azioni Livewire, dopo la scadenza (3 ore), non solo all'apertura.
- `.env.example` propone `SESSION_ENCRYPT=true` e `SESSION_SECURE_COOKIE=true`; i cookie sono
  `HttpOnly` e `SameSite=Lax`.

## 6. Server diversi da Apache

`public/.htaccess` vale solo per Apache e LiteSpeed (che lo legge). Con **Nginx** da solo:

- cartella pubblica = `public/`, tutto il resto fuori dalla radice del sito;
- nessun accesso a file nascosti (`location ~ /\. { deny all; }`) né a `storage/`;
- intestazioni per i file statici: `add_header X-Content-Type-Options nosniff always;` e le
  altre del §1 (le pagine le ricevono già dall'app);
- reindirizzamento su HTTPS e `client_max_body_size` almeno pari a `MANUTRACK_UPLOAD_MAX_MB`.

La cartella delle copie di sicurezza (`storage/app/backups`) è fuori da `public/` e ha anche un
`.htaccess` con `Require all denied`.

## 7. Verifica in due passaggi obbligatoria per gli amministratori

*Impostazioni › Azienda › Verifica in due passaggi*, solo l'amministratore, e solo se l'ha già
attivata sul proprio account (altrimenti resterebbe chiuso fuori). Con l'obbligo attivo:
- un amministratore senza verifica viene portato al suo profilo con l'avviso e non può fare
  altro (anche le azioni delle pagine già aperte rispondono 403) finché non la attiva;
- un amministratore non può disattivare la propria; se perde il telefono usa i codici di
  recupero o chiede a un altro amministratore di azzerarla (al successivo accesso dovrà
  riattivarla);
- la pagina elenca gli amministratori che non l'hanno ancora attivata; attivazione e
  disattivazione dell'obbligo restano nel registro attività.

## 8. APP_URL

`APP_URL` deve essere l'indirizzo pubblico in `https://` con lo stesso dominio con cui si apre
l'app. L'installazione dal browser non parte se non lo è; `manutrack:install` si ferma se in
produzione non è `https` o è un indirizzo locale; la pagina Sistema lo segnala in rosso. Tutti
gli indirizzi generati dall'app (link nelle email, QR) partono da qui.

