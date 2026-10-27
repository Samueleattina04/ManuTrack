# Prova su iPhone e Android reali

Questa guida serve a provare su telefoni veri l'app installabile e la notifica push di prova (fase 3), poi gli avvisi urgenti, i QR e le foto delle macchine (fase 4, paragrafo 9).
Ci vuole circa mezz'ora la prima volta.

Indice:
1. Sottodominio con HTTPS su DirectAdmin
2. Chiavi VAPID per l'installazione
3. Controlli dal PC prima dei telefoni
4. Checklist iPhone
5. Checklist Android
6. Problemi frequenti
7. In alternativa: tunnel HTTPS verso il PC
8. Cosa è già stato verificato e cosa no
9. Fase 4: avvisi urgenti, QR e foto dal telefono

Negli esempi `prova.example.com` è il sottodominio di prova: sostituiscilo con il tuo.

---

## 1. Sottodominio con HTTPS su DirectAdmin

Senza HTTPS valido **niente funziona**: né l'installazione sul telefono, né il service worker, né le notifiche. Un certificato autofirmato non basta: i telefoni lo rifiutano.

### 1.1 Creare il sottodominio
1. Accedi a DirectAdmin come utente (livello *User*).
2. *Account Manager › Subdomain Management › Add Subdomain*.
3. Scrivi `prova` e conferma. Nasce `prova.example.com`.
   - Su molti server la cartella è `domains/example.com/public_html/prova`.
   - Su altri è `domains/prova.example.com/public_html`.
   La colonna *Directory* della tabella dice quale.
4. Se il DNS del dominio non è gestito da questo server, aggiungi presso chi gestisce il DNS un record **A** `prova` verso l'IP del server.
   - L'IP compare in DirectAdmin, nel riquadro informazioni dell'account.
5. Attendi che `http://prova.example.com` risponda (pochi minuti, fino a qualche ora se il DNS è esterno).

### 1.2 Certificato Let's Encrypt
1. *Account Manager › SSL Certificates*. Se hai più domini, scegli quello principale in alto.
2. Scegli **Free & automatic certificate from Let's Encrypt** (in alcune versioni *Get automatic certificate from ACME Provider*).
3. Nell'elenco dei nomi spunta **`prova.example.com`** (e `www.prova…` solo se esiste un record DNS per quel nome).
4. Premi **Save**/**Request**. Dopo circa un minuto compare "Certificate and key saved".
   - Se fallisce, quasi sempre il DNS non punta ancora al server: riprova più tardi.
5. Il rinnovo è automatico ogni 60–90 giorni: non devi fare altro.

### 1.3 Forzare HTTPS
1. *Account Manager › Domain Setup*, clic sul dominio.
2. Spunta **Force SSL with https redirect** e salva.
   - Se l'opzione è grigia, il certificato non è ancora attivo.
3. In ogni caso il file `public/.htaccess` di ManuTrack reindirizza su HTTPS e invia l'intestazione HSTS.

### 1.4 Cartella pubblica del sottodominio
Dal web deve essere raggiungibile solo `manutrack/public`. Procedi così:
1. Carica il pacchetto in una cartella **accanto** a quella del sottodominio, per esempio `domains/example.com/manutrack-prova/`. Non caricarlo dentro `public_html/prova`.
2. Fai puntare la cartella del sottodominio a `manutrack-prova/public`. È il metodo A di [`INSTALLAZIONE.md`](INSTALLAZIONE.md#7-cartella-pubblica-tre-metodi), con la voce di cron temporanea se non hai SSH:
   ```
   cd /home/<utente>/domains/example.com/public_html && mv prova prova.vecchia && ln -s ../manutrack-prova/public prova
   ```
   Togli la voce dal cron appena il sito risponde.
3. DirectAdmin serve l'HTTPS da `private_html`.
   - Di solito `private_html` è un collegamento a `public_html`, quindi non devi fare nulla.
   - Se non lo è, ripeti il collegamento anche in `private_html`, altrimenti in HTTPS vedi una pagina vuota o di default.

### 1.5 Database, `.env`, cron e installazione
Segui [`INSTALLAZIONE.md`](INSTALLAZIONE.md) ai paragrafi 4, 8, 9 e 10. Per la prova contano:

```
APP_URL=https://prova.example.com      # esattamente l'indirizzo HTTPS, senza / finale
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
VAPID_SUBJECT="mailto:tuo-indirizzo@example.com"   # un tuo indirizzo email vero
```

Controllo rapido dal browser del PC:
- [ ] `https://prova.example.com` mostra il lucchetto senza avvisi.
- [ ] `http://prova.example.com` porta su `https://`.
- [ ] `https://prova.example.com/sw.js` mostra il codice del service worker (non una pagina HTML).
- [ ] `https://prova.example.com/manifest.webmanifest` mostra un testo JSON con `"name"`.

---

## 2. Chiavi VAPID per l'installazione

Le chiavi VAPID sono una coppia pubblica/privata che identifica **questa** installazione presso i servizi push di Apple, Google e Mozilla.
- Ogni cliente deve avere le proprie chiavi.
- Le chiavi stanno solo nel file `.env` del server: non vanno mai nel repository né nel pacchetto.

Scegli **uno** dei modi seguenti.

**A. Durante l'installazione dal browser (il più semplice)**

In `/install` lascia spuntato *Genera le chiavi per le notifiche push*. La pagina finale conferma che sono state scritte in `.env`.

**B. Da riga di comando (SSH o Terminale del pannello)**

```bash
cd ~/domains/example.com/manutrack-prova
php artisan manutrack:vapid          # genera e scrive in .env
php artisan config:clear             # rilegge .env
php artisan manutrack:vapid --show   # controllo: stampa la chiave pubblica
```

**C. Senza SSH, con una voce di cron una sola volta**

1. In *Advanced Features › Cron Jobs* aggiungi una voce temporanea che parta tra due minuti (usa il percorso di PHP 8.3 del tuo server):
   ```
   cd /home/<utente>/domains/example.com/manutrack-prova && /usr/local/bin/php artisan manutrack:vapid --no-interaction && /usr/local/bin/php artisan config:clear
   ```
2. Dopo che è partita, **cancella la voce**: rieseguita, si fermerebbe comunque perché le chiavi esistono già.

**D. Se `.env` non è scrivibile**

Il comando stampa due righe `VAPID_PUBLIC_KEY=…` e `VAPID_PRIVATE_KEY=…`.
1. Con il cron le trovi nell'email di DirectAdmin o nel file indicato con `>> …/vapid.txt`. Cancella quel file subito dopo.
2. Copiale in `.env` con *File Manager › Edit*.
3. Esegui `php artisan config:clear` (anche con la voce di cron una tantum).

**Verifica.** In ManuTrack, la pagina **Sistema** deve indicare "notifiche push: chiavi configurate".

**Attenzione.**
- `--force` sostituisce le chiavi: tutti i dispositivi già iscritti smettono di ricevere e devono rifare "Attiva notifiche". Usalo solo se la chiave privata è finita dove non doveva.
- La chiave **privata** non va mai incollata in chat, email o ticket. La pubblica invece non è segreta.

---

## 3. Controlli dal PC prima dei telefoni

Con Chrome o Edge sul PC, accedi a `https://prova.example.com`:
- [ ] Il menu del profilo porta ad **App e notifiche** (`/app`) e **Diagnostica notifiche** (`/notifiche/diagnostica`).
- [ ] In diagnostica le voci "Connessione sicura", "Service worker attivo" e "Chiavi push del server" sono verdi.
- [ ] **Attiva notifiche**: il browser chiede il permesso (solo dopo il clic, mai all'apertura della pagina). Consenti.
- [ ] **Prova su questo dispositivo**: arriva "ManuTrack: notifica di prova". Il clic sulla notifica apre la diagnostica.

Se sul PC funziona, chiavi e server sono a posto: eventuali problemi sui telefoni dipendono dal dispositivo.

---

## 4. Checklist iPhone

Requisiti:
- iOS o iPadOS **16.4 o successivo** (*Impostazioni › Generali › Info › Versione iOS*).
- Su iPhone le notifiche web funzionano **solo** se l'app è aggiunta alla schermata Home e aperta dall'icona: in Safari "normale" non sono disponibili.

### 4.1 Installazione
- [ ] Apri **Safari** (non Chrome né un'altra app) e vai su `https://prova.example.com`. Accedi.
- [ ] La panoramica mostra il riquadro *Installa ManuTrack sul telefono*: tocca **Inizia** (oppure vai su Profilo › App e notifiche).
- [ ] Segui il passo 1 della procedura: tocca **Condividi** (quadrato con freccia in su) › scorri › **Aggiungi alla schermata Home** › **Aggiungi**.
  - Su iOS 18 la voce può trovarsi sotto *Altre azioni*.
- [ ] Sulla schermata Home c'è l'icona con la M nei colori dell'azienda.
- [ ] **Chiudi Safari** e apri ManuTrack **dall'icona**.
  - All'avvio compare per un attimo la schermata scura con la M.
  - Non c'è barra degli indirizzi.
  - Se chiede di nuovo l'accesso è normale: l'app installata ha dati separati da Safari.

### 4.2 Notifiche
- [ ] Nell'app: Profilo › **App e notifiche**. La procedura è al passo 2 e mostra "ManuTrack è installata e aperta come app".
- [ ] Tocca **Attiva notifiche** › nel messaggio di iOS tocca **Consenti**.
- [ ] Passo 3: tocca **Invia notifica di prova**.
- [ ] **Blocca l'iPhone** o torna alla schermata Home: entro qualche secondo arriva la notifica "ManuTrack: notifica di prova".
  - Con l'app aperta in primo piano iOS può mostrarla solo nel Centro notifiche.
- [ ] Tocca la notifica: si apre ManuTrack sulla pagina *Diagnostica notifiche*.
- [ ] L'icona sulla schermata Home mostra il **pallino con 1**; sparisce quando apri l'app.

### 4.3 Diagnostica e altro
- [ ] *Diagnostica notifiche*: tutte le voci verdi (app installata sì, permesso concesso, iscrizione sul dispositivo e sul server sì).
- [ ] L'elenco "I miei dispositivi iscritti" mostra "iPhone · Safari" con l'ultimo invio riuscito.
- [ ] **Senza rete**: attiva *Uso in aereo*, apri l'app e cambia pagina. Compare la pagina "Sei offline" con **Riprova**; con la rete di nuovo attiva si ricarica da sola.
- [ ] **Nuova versione**: dopo un aggiornamento del server, alla riapertura dell'app compare in basso "Nuova versione disponibile". Tocca **Aggiorna**: la pagina si ricarica e la versione nel piè di pagina cambia.
- [ ] Se qualcosa non va: in diagnostica tocca **Copia rapporto** e incollalo in una nota o email.

### 4.4 Riattivare le notifiche negate su iPhone
*Impostazioni › Notifiche › ManuTrack* › attiva **Consenti notifiche** (e *Badge* per il pallino).
Se ManuTrack non compare nell'elenco:
1. rimuovi l'icona dalla schermata Home;
2. aggiungila di nuovo da Safari;
3. rifai "Attiva notifiche".

---

## 5. Checklist Android

Requisiti:
- Android 8 o successivo, con **Chrome** aggiornato.
- Funzionano anche Edge, Samsung Internet e Firefox, ma la guida segue Chrome.

### 5.1 Installazione
- [ ] Apri **Chrome** e vai su `https://prova.example.com`. Accedi.
- [ ] Profilo › **App e notifiche**: tocca **Installa ManuTrack** e poi **Installa** nella finestra di Chrome.
  - Se il pulsante non c'è, usa **⋮ › Installa app** (o *Aggiungi a schermata Home › Installa*).
- [ ] Nel cassetto delle app e sulla schermata Home c'è ManuTrack con l'icona adattiva (la M non viene tagliata dalla forma dell'icona).
- [ ] Apri ManuTrack dall'icona: si apre a schermo intero, con la barra di stato scura dell'app.

### 5.2 Notifiche
- [ ] Nella procedura tocca **Attiva notifiche** › **Consenti**.
  - Su Android 13 e successivi può comparire prima il permesso di sistema per Chrome: consenti anche quello.
- [ ] Tocca **Invia notifica di prova**, poi spegni lo schermo: la notifica arriva con l'icona di ManuTrack.
- [ ] Toccala: si apre la diagnostica nell'app installata.
- [ ] Sull'icona compare il **pallino** (dipende dal launcher: su alcuni è solo un punto senza numero).
- [ ] *Diagnostica notifiche*: voci verdi, dispositivo "Android · Chrome" nell'elenco.
- [ ] Prova offline e "Nuova versione disponibile" come per iPhone (4.3).

### 5.3 Riattivare le notifiche negate su Android
- Nell'app: tocca il menu ⋮ (se visibile) › *Impostazioni sito › Notifiche › Consenti*.
- Oppure: *Impostazioni Android › App › ManuTrack* (o *Chrome*) › *Notifiche* › attiva.
- Con il **risparmio energetico** attivo, le notifiche possono arrivare in ritardo: per la prova disattivalo.

---

## 6. Problemi frequenti

| Sintomo | Causa probabile e rimedio |
|---|---|
| Sull'iPhone manca "Aggiungi alla schermata Home" | Stai usando un'app diversa da Safari, o un'anteprima di link (da WhatsApp, Mail…). Apri il link direttamente in Safari. |
| Su iPhone "Attiva notifiche" non compare o la diagnostica dice "serve l'app installata" | Hai aperto il sito in Safari invece che dall'icona sulla schermata Home. |
| Diagnostica: "Chiavi push del server: no" | Mancano le chiavi VAPID o la configurazione è in cache: vedi paragrafo 2, poi `php artisan config:clear`. |
| Diagnostica: "Service worker attivo: no" | Ricarica la pagina una volta. Se resta "no": controlla `https://…/sw.js` (deve essere JavaScript) e che il sito sia in HTTPS valido. |
| "Notifica inviata a 0 dispositivi" | L'iscrizione è scaduta o le chiavi sono cambiate: in diagnostica **Disattiva su questo dispositivo** e poi **Attiva notifiche**. ManuTrack elimina da solo le iscrizioni rifiutate dal servizio push. |
| La notifica arriva solo con l'app aperta | Modalità *Full immersion*/*Non disturbare* o risparmio energetico. Su iPhone controlla anche *Impostazioni › Notifiche › ManuTrack › Stile avvisi*. |
| Errore di invio con "timeout" | L'hosting blocca le connessioni in uscita verso i servizi push (porta 443). Chiedi al supporto di consentire `*.push.apple.com`, `fcm.googleapis.com`, `updates.push.services.mozilla.com`, `*.notify.windows.com`. |
| L'icona o i colori non sono aggiornati dopo un cambio di palette | iOS conserva l'icona aggiunta alla Home: rimuovila e aggiungila di nuovo. |

---

## 7. In alternativa: tunnel HTTPS verso il PC

Utile per provare prima di caricare sull'hosting.
1. Avvia ManuTrack in locale: `php artisan serve`.
2. Crea un tunnel HTTPS, per esempio:
   - `cloudflared tunnel --url http://localhost:8000`
   - oppure `ngrok http 8000`.
3. Imposta in `.env` `APP_URL=` all'indirizzo `https://…` del tunnel ed esegui `php artisan config:clear`.
4. Segui le checklist 4 e 5 con quell'indirizzo.
5. Con i tunnel gratuiti l'indirizzo cambia a ogni avvio: l'app installata va rimossa e aggiunta di nuovo.

---

## 8. Cosa è già stato verificato e cosa no

Verificato in sviluppo, nel browser Chromium automatico (`node scripts/e2e/pwa-check.cjs`):
- Service worker:
  - attivo e in controllo della pagina;
  - file dell'app in cache;
  - pagina "Sei offline" senza rete;
  - avviso "Nuova versione disponibile" con aggiornamento solo su richiesta.
- Manifest e icone corretti.
- Nessuna richiesta di permesso all'apertura delle pagine.
- Procedura guidata e diagnostica, simulando iPhone (Safari e app installata) e Android.

Verificato con i test automatici (Pest):
- Iscrizione, rinnovo e rimozione dei dispositivi.
- Invio con cifratura VAPID reale verso un servizio push finto locale.
- Rimozione automatica delle iscrizioni rifiutate (410) o fallite 5 volte.
- Funzionamento anche senza le estensioni PHP `bcmath` e `gmp`.

**Da verificare con le checklist qui sopra, su telefoni veri:**
- l'installazione sulla schermata Home;
- la consegna reale tramite i servizi push di Apple e Google (dal sandbox di sviluppo non sono raggiungibili);
- il pallino sull'icona;
- le schermate di avvio iPhone.

---

## 9. Fase 4: avvisi urgenti, QR e foto dal telefono

Servono due persone, cioè due account:
- un **responsabile** (ruolo "Responsabile manutenzione"), con l'app installata e le notifiche attive sul telefono, come ai paragrafi 4 e 5;
- un **tecnico**, che può usare il PC o un secondo telefono.

Crea due macchine di prova (*Macchine › Nuova macchina*):
- `PRS-01` con criticità **A**;
- `AVV-01` con criticità **C**.

### 9.1 Notifica urgente (macchina che ferma la linea)
- [ ] Sul telefono del responsabile **blocca lo schermo**.
- [ ] Il tecnico apre una macchina di **criticità A** (es. `PRS-01`) › *Stato della macchina* › **In guasto**.
  - Sotto compare l'avviso rosso "i responsabili ricevono subito una notifica urgente".
  - Scrive una nota e conferma.
- [ ] Entro pochi secondi, senza aspettare il cron, sul telefono del responsabile arriva "Linea ferma · PRS-01 … In guasto", con la nota e il nome del tecnico.
- [ ] Toccando la notifica si apre la scheda della macchina.
- [ ] La pagina del tecnico si è aggiornata subito, senza restare in attesa.
  - Se resta qualche secondo in caricamento, guarda la voce "Push urgenti" nella pagina **Sistema** (vedi `INSTALLAZIONE.md`, "Notifiche urgenti").
- [ ] Controprova:
  1. togli per un momento la voce del cron;
  2. rimetti `PRS-01` operativa e poi di nuovo in guasto: la notifica urgente arriva lo stesso;
  3. rimetti la voce del cron.

### 9.2 Notifica normale (in coda)
- [ ] Con il cron attivo, il tecnico mette **Ferma** una macchina di **criticità C** (es. `AVV-01`).
- [ ] La notifica arriva entro circa un minuto, al giro di cron successivo.
- [ ] Rimettendo la macchina **Operativa** arriva "… di nuovo operativa", sempre in coda.
- [ ] Chi cambia lo stato non riceve la notifica sul proprio telefono.

### 9.3 QR ed etichette
- [ ] Da *Macchine* scegli due o tre macchine › **Stampa etichette** › formato "Medie" › **Stampa**.
  - Nella finestra di stampa scegli scala 100% e nessun margine.
- [ ] Con la **fotocamera** del telefono inquadra il QR:
  - si apre la scheda della macchina;
  - se non avevi fatto l'accesso, passa prima dal login e poi arriva alla scheda.
  - Su Android, con l'app installata, il link si apre nell'app; su iPhone si apre in Safari.
- [ ] Dalla scheda tocca **Genera un nuovo codice** e inquadra di nuovo l'etichetta vecchia: deve dire che il codice non corrisponde più a nessuna macchina.

### 9.4 Foto e contatori dal telefono
- [ ] Nella scheda di una macchina tocca **Aggiungi foto** e scatta una foto.
  - Dopo il caricamento compare la miniatura, e la prima foto diventa la principale.
- [ ] Scarica la foto dal PC e guardane le proprietà: lato lungo 1600 px, **nessuna posizione GPS**.
- [ ] Aggiungi un PDF (manuale) con **Aggiungi documenti** e riaprilo.
- [ ] Registra una lettura del contatore scrivendo "1.250,5". Poi prova una lettura più bassa: viene rifiutata con la spiegazione.
- [ ] Su iPhone, se la foto viene rifiutata come HEIC: *Impostazioni › Fotocamera › Formati › Più compatibile*.
