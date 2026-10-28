# Dati personali e GDPR in ManuTrack

Strumenti per l'azienda cliente (titolare del trattamento) e per chi gestisce l'installazione
(responsabile). Tutto è in **Sistema › Dati e privacy**, visibile a chi ha il permesso
`data.export` (di serie solo l'**amministratore**), con la password riconfermata.
Anonimizzazione e cancellazione totale sono riservate al ruolo amministratore.

I modelli dei documenti (informativa, nomina art. 28, sub-responsabili, condizioni di servizio)
sono in [docs/legale/](legale/README.md): **bozze da far rivedere a un avvocato**.

## 1. Quali dati personali tratta ManuTrack

| Dove | Dati |
|---|---|
| Persone (`users`) | nome, email, telefono, mansione, lingua, ruolo, ultimo accesso, verifica in due passaggi |
| Registro attività (`audit_logs`) | chi ha fatto cosa e quando, indirizzo IP, browser |
| Lavoro | ordini assegnati, commenti, tempi di lavoro, prelievi, letture, segnalazioni (chi le ha inviate) |
| Testi liberi | descrizioni, note e commenti possono contenere nomi o numeri scritti a mano |
| Avvisi e dispositivi | avvisi ricevuti, preferenze, iscrizioni alle notifiche push |
| Fornitori | referente, email, telefono |
| File | foto (senza GPS, tolto al caricamento) e documenti allegati |
| Inviti | nome ed email della persona invitata |

Nessun cookie di profilazione, nessuna statistica esterna, nessuna risorsa da siti terzi.

## 2. Esportazione completa (portabilità, restituzione a fine contratto)

**Sistema › Dati e privacy › Esportazione › Prepara l'esportazione.** Il file si prepara in
coda (con il cron, vedi [INSTALLAZIONE.md](INSTALLAZIONE.md) §9) e si scarica dalla stessa
pagina; resta sul server **7 giorni**, poi la procedura notturna lo cancella. Ogni richiesta e
ogni download finiscono nel registro attività.

Contenuto dello ZIP:

| Percorso | Cosa |
|---|---|
| `INDICE.md` | indice leggibile: tabelle con numero di righe e descrizione, file collegati, cosa è escluso |
| `indice.json` | lo stesso indice per i programmi, con versione, data e impronta SHA-256 di ogni file |
| `dati/<tabella>.json` | una tabella per file, tutte le righe |
| `file/allegati/<id>-<nome>` | foto e documenti, con il collegamento (`linked_to`) a macchina, ordine o segnalazione |
| `file/modelli/<id>-<codice>.glb` | modelli 3D delle macchine |
| `file/logo/` | logo dell'azienda |

Esclusi di proposito: password e codici della verifica in due passaggi, token degli inviti e
dei link di reimpostazione, sessioni, iscrizioni push, code interne, impostazioni con chiavi o
segreti (chiavi VAPID, password della posta). Le esclusioni sono elencate anche nell'indice.

> Lo ZIP contiene dati personali: chi lo scarica ne diventa custode. ManuTrack non può
> controllare né cancellare le copie scaricate.

## 3. Anonimizzazione di una persona

**Persone › (persona disattivata) › Altro › Anonimizza…**, oppure **Sistema › Dati e privacy ›
Anonimizzazione**.

1. Prima si **disattiva** la persona (non si anonimizza un account attivo, né il proprio).
2. Si possono aggiungere **altre forme del nome** da cercare nei testi (es. "M. Rossi", un
   soprannome, un numero di telefono), almeno 3 caratteri ciascuna.
3. L'**anteprima** mostra in quali campi e in quante righe compare.
4. Si conferma scrivendo `ANONIMIZZA`.

Effetti:

- l'account diventa **"Persona anonimizzata #id"**, con email `anonimo-<id>@anonimo.invalid`,
  senza telefono, mansione, ruoli, permessi, password utilizzabile, verifica in due passaggi;
  sessioni, dispositivi push, avvisi e preferenze vengono cancellati; non si può riattivare;
- nome, email, telefono e le forme aggiunte vengono sostituiti dal segnaposto in tutti i testi
  liberi (ordini, segnalazioni, commenti, note di tempi, fermi, letture, movimenti, checklist,
  avvisi, registro attività anche dentro le modifiche registrate, nomi dei file allegati,
  fornitori, note delle macchine e dei piani, inviti);
- le righe del registro attività fatte dalla persona perdono **indirizzo IP e browser**;
- **storico e indicatori restano coerenti**: l'id non cambia, quindi ordini assegnati, tempi,
  segnalazioni e costi restano dove erano e MTTR, MTBF, arretrato e costi non cambiano;
- il registro attività annota l'operazione (`users.anonymized`) con il segnaposto e i soli
  conteggi delle sostituzioni, mai i dati tolti.

**Persona senza account** (per esempio un nome scritto nelle note): scegliere "Nessun
account" e scrivere il nome; si sostituisce con `[persona anonimizzata]`.

Limiti, da gestire a mano:

- **foto e documenti** non vengono letti: se ritraggono o nominano la persona vanno controllati
  ed eliminati dalla scheda della macchina o dell'ordine;
- un nome scritto con un errore di battitura si trova solo se si aggiunge quella forma;
- **copie di sicurezza, esportazioni già scaricate ed email inviate** non cambiano (§6).

## 4. Conservazione

**Sistema › Dati e privacy › Conservazione.** Vuoto significa "per sempre". La procedura gira
ogni notte alle **03:50** (`php artisan manutrack:conservazione`, nella stessa voce di cron di
tutto il resto) e, se cambia qualcosa, lo annota nel registro (`system.retention`).

| Regola | Impostazione | Predefinita |
|---|---|---|
| Anonimizzare le persone disattivate dopo N mesi dalla disattivazione | `retention.anonymize_after_months` | mai |
| Conservare il registro attività per N mesi (minimo 6) | `retention.audit_months` | per sempre |
| Avvisi nel centro avvisi | fissa | 90 giorni |
| ZIP delle esportazioni complete | fissa | 7 giorni |
| Copie di sicurezza di ManuTrack | `MANUTRACK_BACKUPS_KEEP` nel `.env` | le ultime 5 |

La pagina mostra in anticipo quante persone e quante righe del registro verrebbero toccate la
notte successiva. I dati del lavoro (ordini, tempi, ricambi, letture) non hanno una scadenza
automatica: servono agli obblighi di manutenzione e agli indicatori; per toglierne i dati
personali si usa l'anonimizzazione.

## 5. Cancellazione totale a fine contratto

**Sistema › Dati e privacy › Cancellazione totale** (solo amministratore). La pagina mostra
cosa verrà cancellato (righe e tabelle del database, file e dimensioni per cartella), avvisa se
non c'è un'esportazione degli ultimi 7 giorni, e chiede:

1. la spunta di presa d'atto;
2. la frase esatta `CANCELLA <NOME DELL'AZIENDA>` in maiuscolo;
3. la password dell'amministratore;
4. un'ultima conferma.

Dal terminale (per chi gestisce l'installazione):

```bash
php artisan manutrack:cancella-tutto --conferma="CANCELLA NOME AZIENDA"
```

Vengono cancellati: **tutte le tabelle del database**; allegati, modelli 3D, logo, esportazioni
(`storage/app/private`), file pubblici, **copie di sicurezza di ManuTrack**
(`storage/app/backups`), registri del server (`storage/logs`), sessioni e cache.

Resta solo `storage/app/manutrack-cancellata.json`, il **rapporto**: data, modo (pagina o
terminale), chi l'ha chiesto (solo "amministratore (id N)" o "comando dal terminale"), righe
per tabella, file per cartella, elenco di ciò che resta fuori. Nessun dato personale.
Da quel momento ogni indirizzo dell'app risponde con il rapporto (stato HTTP 410) e il cron non
esegue più nulla.

Per chiudere davvero l'installazione, dopo il rapporto, dal pannello dell'hosting:

1. scaricare il rapporto (`storage/app/manutrack-cancellata.json`) e consegnarlo al cliente;
2. eliminare il **database** e il suo utente (DirectAdmin › *MySQL Management*);
3. eliminare la **cartella dell'installazione**, compreso il file `.env` con le password;
4. togliere la **voce di cron** e il sottodominio;
5. eliminare le **copie dell'hosting** (§6).

## 6. Cosa resta fuori dal controllo di ManuTrack

Né l'anonimizzazione né la cancellazione totale possono raggiungere:

| Cosa | Chi ne risponde | Come si elimina |
|---|---|---|
| Copie di sicurezza fatte dall'**hosting** (DirectAdmin *Create/Restore Backups*, copie automatiche del fornitore) | chi gestisce l'hosting | dal pannello, *Create/Restore Backups* › elimina; per le copie automatiche del fornitore, chiederne la cancellazione e la data di scadenza per iscritto |
| Copie di ManuTrack **già spostate altrove** (scaricate o copiate su un altro disco) | chi le ha copiate | eliminarle a mano dove sono |
| **Esportazioni** ed elenchi CSV/XLSX scaricati, rapporti mensili stampati | le persone dell'azienda | sotto la loro custodia |
| **Email** e notifiche già inviate | destinatari e servizio di posta | caselle di posta dei destinatari |

### Eliminare le copie vecchie di ManuTrack

Le copie (`.sql.gz`, solo database) sono in `storage/app/backups/`, fatte prima di ogni
aggiornamento, prima di ogni ripristino e a mano con `php artisan manutrack:backup`. Contengono
i dati **com'erano** al momento della copia, anche di persone poi anonimizzate.

- `MANUTRACK_BACKUPS_KEEP` nel `.env` (predefinito 5) è il numero di copie tenute: a ogni copia
  nuova le più vecchie oltre quel numero vengono cancellate.
- Per vedere quali ci sono: `php artisan manutrack:backup --list`.
- Per toglierle subito, senza SSH: *File Manager* di DirectAdmin › cartella dell'installazione ›
  `storage/app/backups/` › selezionare i file `manutrack-*.sql.gz` più vecchi › *Delete*.
  Con SSH: `rm storage/app/backups/manutrack-AAAAMMGG-*.sql.gz`.
- Dopo un'anonimizzazione che deve valere anche per le copie, eliminare le copie precedenti e,
  se serve averne una, farne una nuova con `php artisan manutrack:backup`.

## 7. Diritti degli interessati: dove si fa cosa

| Richiesta | In ManuTrack |
|---|---|
| Accesso / copia (art. 15) | esportazione completa (§2), poi estrarre le righe della persona; il profilo mostra i dati dell'account |
| Rettifica (art. 16) | Persone › modifica, oppure la persona dal proprio profilo |
| Cancellazione (art. 17) | disattivazione e anonimizzazione (§3), nei limiti degli obblighi di conservazione |
| Limitazione (art. 18) | disattivazione dell'account |
| Portabilità (art. 20) | esportazione completa, file JSON |
| Fine del contratto | esportazione, poi cancellazione totale (§5) e copie dell'hosting (§6) |

## 8. Per chi sviluppa

- `App\Support\Gdpr\DataExporter`, `PersonAnonymizer` (colonne in `TEXT_COLUMNS`: una tabella
  nuova con testi liberi o dati di persone **va aggiunta lì** e, se contiene segreti, alle
  esclusioni dell'esportazione), `Retention`, `Eraser`.
- Middleware `EnsureNotErased` in testa al gruppo `web`; `routes/console.php` non registra
  pianificazioni dopo la cancellazione.
- Test in `tests/Feature/Gdpr/`.
