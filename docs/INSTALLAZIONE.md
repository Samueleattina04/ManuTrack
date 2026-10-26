# Installazione e aggiornamento di ManuTrack

Questa guida spiega come creare una **nuova installazione per un cliente** su un hosting con pannello **DirectAdmin**, come configurare HTTPS, il cron e le notifiche push, e come aggiornare. Non serve accesso SSH, Docker o Node sul server: bastano **PHP 8.3** e **MySQL 8 o MariaDB 10.6+**.

> Ogni cliente ha una cartella sua con una copia dell'app, un database suo e un file `.env` suo. Le installazioni sono indipendenti tra loro.

**Indice**
1. Requisiti dell'hosting
2. Preparare il pacchetto (sul tuo PC)
3. Dominio o sottodominio
4. Database
5. HTTPS con Let's Encrypt
6. Caricare i file
7. Cartella pubblica (tre metodi)
8. File `.env`
9. Cron (una sola voce)
10. Installazione
11. Notifiche push (chiavi VAPID)
12. Verifica finale
13. Aggiornamenti
14. Copie del database e ripristino
15. Problemi frequenti
16. Sviluppo in locale

---

## 1. Requisiti dell'hosting

| Requisito | Note |
|---|---|
| PHP **8.3** | In DirectAdmin: *Account Manager › Domain Setup › PHP Version* (o *Select PHP Version*). |
| Estensioni PHP indispensabili | `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `json`, `filter` |
| Estensioni consigliate | `gd` **o** `imagick` (foto e icone), `zip` (esportazioni), `intl`, `bcmath` o `gmp` (push) |
| Database | MySQL 8 o MariaDB 10.6+, con charset `utf8mb4` |
| HTTPS | Obbligatorio: senza HTTPS l'app non si installa sui telefoni e le notifiche non funzionano |
| Cron | Una voce ogni minuto (*Advanced Features › Cron Jobs*) |
| Spazio | circa 40 MB per l'app, più foto e documenti caricati |

L'installazione controlla tutti questi requisiti e mostra cosa manca.

**Se mancano sia GD sia Imagick**, l'app funziona comunque:
- usa le icone già pronte nel pacchetto per le tre palette (*Grafite e Rame*, *Acciaio e Ciano*, *Ardesia e Indaco*);
- con un colore personalizzato usa le icone della palette più vicina;
- salva le foto senza ridimensionarle;
- la pagina Sistema lo segnala.

Di solito basta attivare GD dal selettore della versione PHP del pannello.

## 2. Preparare il pacchetto (sul tuo PC)

Sul PC servono: git, PHP 8.3 con l'estensione zip, Composer e Node.js 20 o successivo.

Da Linux o macOS:
```bash
scripts/release.sh
```
Da Windows (PowerShell):
```powershell
powershell -ExecutionPolicy Bypass -File scripts\release.ps1
```

Lo script:
1. prende il commit corrente;
2. installa solo le dipendenze di produzione (`composer install --no-dev`);
3. compila il frontend (`npm run build`);
4. toglie test, sorgenti e file di sviluppo;
5. crea `dist/manutrack-<versione>.zip` e il file `.sha256` con l'impronta, utile per controllare che il caricamento sia integro.

Il pacchetto **non contiene `.env` né dati in `storage/`**: lo stesso zip serve sia per le nuove installazioni sia per aggiornare quelle esistenti.

Lo zip contiene `release.json`, scritto per ultimo, con la versione e l'elenco dei file con la loro dimensione. Serve all'aggiornamento automatico per capire se il caricamento è completo (vedi §13).

## 3. Dominio o sottodominio

In DirectAdmin:
- **Dominio dedicato** (es. `manutenzione.cliente.it`): *Account Manager › Domain Setup › Add New*.
- **Sottodominio** (es. `manutrack.cliente.it`): *Account Manager › Subdomain Management › Add Subdomain*.

DirectAdmin crea la cartella del sito:
- `domains/<dominio>/public_html` per un dominio;
- `domains/<dominio>/public_html/<sottodominio>` per un sottodominio.

Il percorso completo è del tipo `/home/<utente>/domains/<dominio>/`.

## 4. Database

*Account Manager › MySQL Management › Create new Database*:
- nome, ad esempio `manutrack` (diventa `<utente>_manutrack`);
- utente e password generata: annotale per il file `.env`.

Il charset predefinito di DirectAdmin (`utf8mb4`) va bene.

## 5. HTTPS con Let's Encrypt

1. *Account Manager › SSL Certificates*: scegli **Free & automatic certificate from Let's Encrypt**.
2. Seleziona il dominio (e `www.` se lo usi) e conferma. Il rinnovo è automatico.
3. Attiva **Force SSL with https redirect**: in *Domain Setup*, sul dominio, spunta *Force SSL with https redirect*.
4. Verifica che `https://<dominio>` mostri il lucchetto senza avvisi.

Il file `public/.htaccess` di ManuTrack reindirizza comunque su HTTPS e invia l'intestazione HSTS, che dice al browser di usare sempre HTTPS. Nel file `.env` lascia `SESSION_SECURE_COOKIE=true`.

> **Perché è indispensabile.** Il service worker (app installabile, uso senza rete) e le notifiche push funzionano solo in HTTPS. Su iPhone le notifiche richiedono inoltre che l'app sia aggiunta alla schermata Home (iOS 16.4 o successivo).

## 6. Caricare i file

Con *File Manager* di DirectAdmin o un client FTP (per esempio FileZilla, in FTPS):

1. Crea la cartella dell'app **fuori** da `public_html`: `domains/<dominio>/manutrack`.
2. Carica `manutrack-<versione>.zip` in quella cartella.
3. Dal File Manager seleziona lo zip e scegli **Extract**. Controlla che `artisan`, `app/`, `vendor/` ecc. finiscano direttamente in `manutrack/`, non in una sottocartella.
4. Cancella lo zip.

Con l'FTP puoi anche estrarre lo zip sul PC e caricare la cartella. Il caricamento richiede più tempo, perché sono circa 15.000 file.

Permessi: `storage/` e `bootstrap/cache/` devono essere scrivibili dall'utente PHP (775 per le cartelle, 664 per i file). Su DirectAdmin di norma lo sono già.

## 7. Cartella pubblica (tre metodi)

Dal web deve essere raggiungibile **solo** `manutrack/public`. Scegli un metodo.

**A. Collegamento `public_html` → `manutrack/public` (consigliato)**

Se hai SSH:
```bash
cd ~/domains/<dominio>
mv public_html public_html.vecchia
ln -s manutrack/public public_html
```
Senza SSH puoi far eseguire lo stesso comando al cron una sola volta:
1. In *Cron Jobs* aggiungi una voce temporanea che parte tra un paio di minuti:
   ```
   cd /home/<utente>/domains/<dominio> && mv public_html public_html.vecchia && ln -s manutrack/public public_html
   ```
2. Quando il sito risponde, **cancella la voce**.

DirectAdmin usa anche `private_html` per HTTPS. Di solito è già un collegamento a `public_html`; se non lo è, ripeti lo stesso passaggio anche per `private_html`.

**B. Sottodominio puntato su `public`**

Per un sottodominio, crea l'app in `public_html/<sottodominio>-app/` e collega la cartella del sottodominio a `.../<sottodominio>-app/public` come nel metodo A. Su alcuni server l'amministratore può impostare direttamente la *document root* del sottodominio.

**C. `.htaccess` di riserva (solo se A e B non sono possibili)**

1. Carica l'app in `public_html/manutrack/`.
2. Copia `deploy/htaccess-public_html.txt` in `public_html/.htaccess`.

Il file inoltra tutto a `manutrack/public/` e blocca l'accesso al resto della cartella.

> Questo metodo è stato verificato meno degli altri due: dopo l'installazione controlla che `https://<dominio>/manutrack/.env` risponda **403** e che `https://<dominio>/manutrack/storage/logs/` non sia leggibile.

## 8. File `.env`

1. Nella cartella `manutrack/` copia `.env.example` in `.env` (File Manager: *Copy*, poi *Rename*).
2. Compila almeno questi valori:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://manutenzione.cliente.it

DB_HOST=localhost
DB_DATABASE=utente_manutrack
DB_USERNAME=utente_manutrack
DB_PASSWORD=la-password-del-database

MAIL_HOST=mail.cliente.it
MAIL_USERNAME=manutrack@cliente.it
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="manutrack@cliente.it"

MANUTRACK_COMPANY_NAME="Nome del cliente"
MANUTRACK_INSTALL_TOKEN=un-codice-lungo-e-casuale
VAPID_SUBJECT="mailto:manutrack@cliente.it"
```

`APP_KEY` può restare vuota: la genera l'installazione. Il file `.env` contiene password: non va mai inviato per email né messo nel repository.

## 9. Cron (una sola voce)

In *Advanced Features › Cron Jobs* aggiungi **una** voce, ogni minuto (tutti i campi `*`):

```
/usr/local/bin/php /home/<utente>/domains/<dominio>/manutrack/artisan schedule:run >> /dev/null 2>&1
```

- Se sul server convivono più versioni di PHP, usa il percorso di PHP 8.3, ad esempio `/usr/local/php83/bin/php`. Il supporto dell'hosting sa indicartelo; in alternativa prova `php83`.
- Questa voce fa tutto:
  - smaltisce la coda di email e notifiche (`queue:work --stop-when-empty`, poi si ferma);
  - genera i piani preventivi;
  - fa le pulizie;
  - applica gli aggiornamenti caricati.
- Nessun processo resta attivo e non servono `exec` o `proc_open`.
- Se il cron non è disponibile, imposta `QUEUE_CONNECTION=sync`. Le email e le notifiche partono subito, ma l'operazione che le genera diventa più lenta, e i piani preventivi automatici e gli aggiornamenti automatici non funzionano.

La pagina **Sistema** dell'app mostra quando il cron ha girato l'ultima volta.

## 10. Installazione

**Dal browser (senza SSH)**
1. Apri `https://<dominio>/install`.
2. Controlla i requisiti: le voci in rosso vanno corrette, quelle in giallo sono consigliate.
3. Inserisci il **codice di installazione**, cioè il valore di `MANUTRACK_INSTALL_TOKEN` in `.env`. Poi compila azienda, colori e amministratore.
4. Premi **Installa**.

Al termine:
- il codice viene cancellato da `.env`;
- viene scritto `storage/app/installed.lock`;
- `/install` risponde 404.

L'installazione si rifiuta di ripartire se trova il file di blocco, utenti nel database o una versione registrata. Anche cancellando il file di blocco, il controllo sul database la tiene disattivata. È previsto anche un limite di 5 tentativi al minuto.

**Da riga di comando (con SSH)**
```bash
php artisan manutrack:install
```
Oppure senza domande, per automatizzare:
```bash
php artisan manutrack:install --company="Nome cliente" --name="Mario Rossi" \
  --email=mario@cliente.it --palette=rame --vapid --no-interaction
```
Senza `--password` il comando la chiede in modo nascosto (consigliato): una password scritta nel comando resta nella cronologia della shell.

## 11. Notifiche push (chiavi VAPID)

Le chiavi identificano il tuo server presso i servizi push di Apple, Google e Mozilla:
- **in installazione:** spunta "Genera le chiavi per le notifiche push" (dal browser) o usa `--vapid` (da riga di comando);
- **in seguito:** `php artisan manutrack:vapid` (`--show` mostra la chiave pubblica, `--force` le sostituisce).

Se `.env` non è scrivibile, il comando stampa le due righe `VAPID_PUBLIC_KEY=` e `VAPID_PRIVATE_KEY=` da copiare a mano.

Imposta `VAPID_SUBJECT` con un indirizzo email reale (`mailto:...`): i servizi push lo usano per contattarti in caso di problemi.

**Non cambiare le chiavi senza motivo:** chi aveva attivato le notifiche dovrebbe riattivarle. Ogni installazione (cliente) ha le sue chiavi.

## 12. Verifica finale

- [ ] `https://<dominio>` apre ManuTrack con il lucchetto e `http://` reindirizza su `https://`.
- [ ] `https://<dominio>/.env` e `https://<dominio>/../.env` non sono raggiungibili.
- [ ] **Sistema**: versione applicata, cron "attivo", requisiti senza voci rosse, notifiche push "chiavi configurate".
- [ ] Dalla fase 3: prova di installazione su un telefono e "Invia notifica di prova".

## 13. Aggiornamenti

1. Sul PC: aggiorna `VERSION` e `CHANGELOG.md`, fai il commit e lancia `scripts/release.sh` (o `.ps1`).
2. Carica lo zip nella cartella `manutrack/` e **estrailo sopra**, sovrascrivendo.

`.env`, i file caricati e le copie del database **non** vengono toccati, perché lo zip non li contiene.

Poi non devi fare altro. **Entro qualche minuto il cron applica l'aggiornamento da solo**, ma solo se:
- `release.json` è presente ed è della stessa versione del file `VERSION`;
- tutti i file elencati in `release.json` esistono con la dimensione giusta, cioè il caricamento è completo;
- nessun file è stato modificato negli ultimi `MANUTRACK_UPDATE_SETTLE_MINUTES` minuti (5 di default);
- non è in corso un altro aggiornamento o ripristino (blocco su file);
- la stessa versione non è già fallita in precedenza.

L'aggiornamento:
1. crea una **copia del database** in `storage/app/backups`, conservando le ultime `MANUTRACK_BACKUPS_KEEP` (5 di default);
2. mette l'app in manutenzione, con la pagina "Aggiornamento in corso";
3. esegue le migrazioni;
4. riallinea i dati di sistema (impostazioni, ruoli…);
5. aggiorna le cache;
6. registra la versione e rimette l'app online.

Di solito dura pochi secondi.

**Con SSH** puoi applicarlo subito:
```bash
php artisan manutrack:update
```
Il comando mostra la versione precedente e la nuova. Rilanciarlo sulla stessa versione non fa nulla ("Già aggiornato").

**Se l'aggiornamento fallisce:**
- l'app esce comunque dalla manutenzione e continua a funzionare;
- l'errore è registrato e mostrato in un avviso e nella pagina **Sistema**, insieme al nome della copia del database fatta subito prima;
- il cron non ritenta da solo la stessa versione.

Dopo aver corretto la causa (per esempio caricando un pacchetto corretto):
```bash
php artisan manutrack:update --retry   # il cron ritenta al giro successivo
php artisan manutrack:update           # oppure: subito
```
Dalla fase 2 c'è anche il pulsante "Ritenta" nella pagina Sistema, per gli amministratori.

**Aggiornare molte installazioni.** Il procedimento è identico e ripetibile su ogni cartella: carica lo stesso zip, estrai, attendi. Un pacchetto caricato due volte, o un aggiornamento lanciato più volte, non produce errori.

## 14. Copie del database e ripristino

Le copie sono file `.sql.gz` scritti in PHP, senza `mysqldump`, in `storage/app/backups/`, una cartella protetta dal web.

```bash
php artisan manutrack:backup              # crea una copia adesso
php artisan manutrack:backup --list       # elenca le copie
php artisan manutrack:restore             # sceglie la copia da un elenco e la ripristina
php artisan manutrack:restore manutrack-20261026-154820-prima-di-0.1.1.sql.gz --force
```

Il ripristino:
1. fa prima una copia dello stato attuale (`prima-del-ripristino`);
2. mette l'app in manutenzione;
3. sostituisce tutte le tabelle;
4. rifiuta file incompleti o danneggiati.

Un aggiornamento rimasto "in corso" nella copia viene segnato come fallito, così il cron non lo ritenta da solo.

Le copie non sostituiscono il backup dell'hosting: tieni attivo anche quello di DirectAdmin (*Create/Restore Backups*), che comprende i file caricati.

## 15. Problemi frequenti

| Sintomo | Causa probabile e soluzione |
|---|---|
| Pagina bianca o "500" subito dopo il caricamento | Estrazione non finita o file mancanti: estrai di nuovo lo zip. Controlla `storage/logs/laravel-*.log`. |
| Il cron risulta "nessun giro registrato" | Percorso di PHP sbagliato nella voce di cron, o PHP diverso da 8.3. Prova il comando dal Terminale del pannello se c'è. |
| L'aggiornamento resta "in attesa" | Leggi il motivo nella pagina Sistema: caricamento incompleto (manca un file), file appena caricati (attendi), oppure versione già fallita (`--retry`). |
| `/install` risponde 404 | L'app è già installata: è il comportamento previsto. |
| Le modifiche a `.env` non hanno effetto | Hai impostato `MANUTRACK_CACHE_CONFIG=true`: lancia `php artisan manutrack:update --force` o rimetti `false`. |
| Le icone non hanno i colori dell'azienda | Manca GD/Imagick: si usano le icone della palette più vicina. Attiva GD dal pannello. |
| Le notifiche non arrivano su iPhone | L'app deve essere aggiunta alla schermata Home (iOS 16.4+) e il permesso concesso da lì. Vedi la pagina diagnostica (dalla fase 3). |

## 16. Sviluppo in locale

```bash
composer install
npm install
cp .env.example .env        # imposta APP_ENV=local, APP_DEBUG=true, i dati del database locale e SESSION_SECURE_COOKIE=false
php artisan key:generate
npm run build               # oppure "npm run dev" durante lo sviluppo
php artisan migrate --seed  # oppure: php artisan manutrack:install
php artisan serve
```

Facoltativo: `docker compose up -d` avvia Apache, PHP 8.3, MariaDB e un cron di prova (vedi `docker-compose.yml`).

**Test:** `php artisan test` su un database MySQL o MariaDB di prova chiamato `manutrack_test`, utente `manutrack`, password `manutrack`; i valori sono modificabili in `phpunit.xml`.

**Qualità:** `vendor/bin/pint` e `vendor/bin/phpstan analyse`.

**Provare l'app installabile e le notifiche su un telefono vero.** Serve HTTPS anche in sviluppo. Il modo più semplice è un tunnel HTTPS verso il PC (ad esempio Cloudflare Tunnel o ngrok): apri l'indirizzo `https://…` dal telefono e imposta lo stesso indirizzo in `APP_URL`. Istruzioni dettagliate nella fase 3.
