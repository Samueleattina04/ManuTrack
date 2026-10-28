# Registro delle modifiche

Tutte le modifiche rilevanti di ManuTrack sono elencate qui.
Formato ispirato a [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/), versioni secondo [SemVer](https://semver.org/lang/it/).

## [0.12.0] - Fase 11: dati e privacy, demo finale

### Aggiunto
- **Dati e privacy** (*Sistema › Dati e privacy*, solo amministratore di serie, password riconfermata), descritto in `docs/GDPR.md`:
  - **esportazione completa** dei dati dell'azienda in uno ZIP preparato in coda: `INDICE.md` e `indice.json` con tabelle, righe e descrizioni, una tabella JSON per file, allegati, modelli 3D e logo con impronta SHA-256 e collegamento alla scheda; esclusi password, codici di accesso, token, sessioni e chiavi; scaricabile 7 giorni; richieste e download nel registro attività;
  - **anonimizzazione di una persona** disattivata (o di un nome senza account) con anteprima di dove compare e conferma scritta: l'account diventa "Persona anonimizzata #id" senza accessi, i testi liberi e il registro attività vengono ripuliti (anche dentro le modifiche registrate), le sue azioni nel registro perdono IP e browser; storico e indicatori restano identici. Collegamento "Anonimizza…" nell'elenco Persone;
  - **conservazione**: anonimizzazione automatica delle persone disattivate dopo N mesi e durata del registro attività (almeno 6 mesi), con anteprima di cosa succederà la notte; comando `manutrack:conservazione` ogni notte alle 03:50;
  - **cancellazione totale** a fine contratto con presa d'atto, frase con il nome dell'azienda, password e ultima conferma (o `manutrack:cancella-tutto --conferma=…`): database e cartelle dei dati cancellati, rapporto senza dati personali di cosa è stato eliminato e di cosa resta fuori; poi l'app mostra solo il rapporto (HTTP 410) e il cron si ferma.
- **Modelli legali** in `docs/legale/`, bozze da far rivedere a un avvocato: informativa privacy per gli utenti, accordo art. 28 GDPR tra cliente e fornitore, elenco dei sub-responsabili (hosting, posta), condizioni di servizio e licenza d'uso dell'installazione.
- Orario di lavoro: un'assegnazione con data passata è consentita solo all'amministratore, con avviso dei mesi degli indicatori che cambiano, conferma esplicita e voce dedicata nel registro attività.
- Demo: macchine disposte in fila lungo le linee e corsie nella pianta; un ex tecnico disattivato da anonimizzare, uno già anonimizzato e regole di conservazione d'esempio.

### Modificato
- CI: il database dei test parte in memoria (tmpfs) senza scritture di sicurezza su disco; stessi test (474), MySQL 8 da circa 23 a 9 minuti, MariaDB da circa 5,5 a 3.

### Corretto
- Test: i dischi `local` e `public` scrivevano nella cartella `storage` del progetto invece che nella cartella temporanea del test.

## [0.11.0] - Orario di lavoro per l'MTBF

### Aggiunto
- **Orario di lavoro** (*Impostazioni › Orario di lavoro*):
  - calendari da modelli pronti (uno, due, tre turni, ciclo continuo) con turni per giorno, anche di notte;
  - versioni "valido dal": un orario in vigore non si modifica, si crea il successivo, così i mesi passati non cambiano;
  - festività nazionali italiane proposte in automatico e modificabili (comprese Pasquetta mobile e, dal 2026, San Francesco il 4 ottobre); chiusure aziendali, patrono e orari speciali a mano; giorni passati bloccati;
  - assegnazione datata all'azienda e alle ubicazioni, con eredità verso le sotto-ubicazioni; alla singola macchina previsto dal calcolo ma non esposto.
- **MTBF sulle ore di lavoro** come valore principale quando c'è un calendario, con l'MTBF sulle 24 ore come valore secondario etichettato: dashboard, tabella per macchina, rapporto mensile, esportazione. Guasti fuori orario contati comunque; turni di notte e ora legale sul tempo reale.
- `docs/KPI.md` §2.9 con formule, eredità, bordi e minimi (3 guasti e 40 ore di lavoro); test con dati noti calcolati a mano (marzo 2026: ora legale, turno di notte, giorno chiuso, orario speciale, cambio di orario, assegnazione a metà mese).
- Comando `manutrack:festivita`, eseguito ogni notte dal cron.
- Orario dimostrativo nei dati di prova (due turni per l'azienda, tre sulla linea 1).

## [0.10.1] - Verifiche dopo la fase 10

### Aggiunto
- Modelli GLB: rifiutati al caricamento quelli che rimandano a file esterni (buffer o immagini con indirizzi web, percorsi relativi o `file:`); il visualizzatore blocca comunque ogni indirizzo diverso dal modello e dai dati incorporati, prima di qualunque richiesta.
- Rotta del modello con `Content-Security-Policy: default-src 'none'; sandbox`; test che il file non sia raggiungibile da indirizzi pubblici.
- Controllo nel browser `scripts/e2e/three-lifecycle-check.cjs` (20 verifiche): rendering fermo con scheda nascosta o vista fuori schermo, memoria e contesto WebGL liberati lasciando la pagina, nessuna richiesta esterna da un GLB malevolo.

### Corretto
- Anteprima 3D della macchina: durante la rotazione automatica ogni fotogramma ne prenotava due, così il lavoro raddoppiava a ogni fotogramma fino a rallentare la pagina; e continuava a disegnare con la scheda nascosta o fuori schermo.
- Le viste 3D si chiudono e liberano la memoria anche quando la pagina va nella cache indietro/avanti del browser, e si riaprono tornando indietro.

## [0.10.0] - Fase 10: 3D

### Aggiunto
- **Stabilimento 3D** (`/stabilimento`, scorciatoia `G T`): piante di stabilimenti o reparti con zone (linee) e macchine colorate per stato, il codice sopra, guasti che pulsano.
  - Un tocco su una macchina apre stato, ordini aperti e i collegamenti a scheda, ordini e "Segnala un guasto".
  - Stati aggiornati da soli ogni 30 secondi.
  - **Vista 2D di riserva** con gli stessi dati (SVG dal server, simboli oltre ai colori): automatica senza WebGL, iniziale sui dispositivi deboli (con "Prova il 3D"), a scelta per tutti; se il 3D non parte si torna al 2D.
  - Elenco accessibile delle macchine sotto la vista.
- **Editor della pianta** (chi gestisce le macchine): trascinamento con mouse o dito, frecce e R da tastiera, griglia di 25 cm, elementi sempre dentro il pavimento anche ruotati; macchine messe da sole nella zona della loro ubicazione; misure, rotazione, zone libere o da sotto-ubicazioni.
- **Vista 3D della macchina** nella scheda, su richiesta: modello **GLB** caricato (glTF 2.0 binario controllato nell'intestazione, fino a `MANUTRACK_MODEL_MAX_MB`, sul disco privato) oppure forma generica della categoria.
- **M di ManuTrack in 3D** nella panoramica e nella pagina di accesso senza logo del cliente: segue mouse o inclinazione del telefono (sull'iPhone dopo un tocco); SVG con "riduci movimento" o su dispositivi deboli.
- Pianta dimostrativa nei dati di prova (solo sviluppo e pacchetto demo).
- Controllo nel browser `scripts/e2e/plant-check.cjs`: pagine operative senza Three.js, 3D/2D secondo il dispositivo, editor, anteprima, riduci movimento.

### Modificato
- Three.js (MIT) in un file a parte scaricato solo dalle pagine 3D; grafici e 3D fuori dalla cache dell'app installata.
- Registro attività: gruppi "Esportazioni" e "Stabilimento" nel filtro.

## [0.9.0] - Fase 9: avvisi completi, grafici, esportazioni e rapporto mensile

### Aggiunto
- **Avvisi completi:**
  - centro avvisi (`/avvisi`) con gli avvisi raggruppati per argomento e giorno, "segna tutti come letti", conservati 90 giorni;
  - dieci eventi (segnalazioni, macchina ferma, ordini assegnati e completati, preventiva da assegnare, scorta minima, aggiornamenti, rapporto mensile), ciascuno visibile solo a chi ha il permesso collegato;
  - **email per ruolo** (*Impostazioni › Avvisi via email*): nessuna, riepilogo giornaliero o subito;
  - **preferenze personali** (*Avvisi › Preferenze*) per push ed email, salvate solo dove differiscono dal ruolo;
  - riepilogo giornaliero via email alle 07:10 (`manutrack:avvisi-riepilogo`);
  - numero degli avvisi da leggere sul menu, sulla barra in basso e sull'icona dell'app installata.
- **Grafici degli indicatori:** ore di fermo, MTTR, preventivo/correttivo e costi per macchina, per settimana o per mese con le stesse regole degli indicatori (`docs/KPI.md` §2.8).
  - Chart.js (MIT) caricato solo nella pagina degli indicatori;
  - tabella equivalente e descrizione testuale per ogni grafico;
  - colori del tema chiaro o scuro, niente animazioni con "riduci movimento".
- **Esportazioni CSV ed Excel** di ordini, macchine, ricambi e indicatori:
  - con i filtri applicati nell'elenco, tutte le pagine (fino a 20.000 righe);
  - permesso "Esportare CSV ed Excel" più quello di vedere l'elenco; colonne dei costi solo a chi vede i costi;
  - **protezione dalle formule** (CSV injection): i testi che cominciano con `=`, `+`, `-`, `@`, tabulazione o a capo restano testo;
  - CSV con `;`, UTF-8 con BOM e decimali con la virgola; Excel con numeri e date veri e un foglio per parte negli indicatori;
  - ogni esportazione nel registro attività.
- **Rapporto mensile** per la direzione (`/indicatori/rapporto`): indicatori del mese, macchine con più fermo, ordini conclusi, ricambi sotto scorta, piani in ritardo. Si stampa o si salva in PDF dal browser, senza programmi sul server. Il 1° del mese chi vede gli indicatori riceve l'avviso (`manutrack:rapporto-mensile`).
- **Proposta** per una fase successiva: orario di lavoro per calcolare l'MTBF sulle sole ore di lavoro previste (`docs/PROPOSTA.md`).

### Modificato
- Filtri di ordini, macchine e ricambi in classi comuni (`app/Support/Filters`), usate sia dagli elenchi sia dalle esportazioni: le due cose non possono divergere.
- Tutti gli avvisi passano da un unico punto (`Notifier`), che decide centro avvisi, push ed email.

### Corretto
- Il conteggio degli avvisi da leggere era conservato per tutto il processo PHP: in un processo che serve più persone (coda, test) si poteva vedere il numero di un altro. Ora vale per la singola richiesta.

## [0.8.0] - Fase 8: dashboard degli indicatori; installazione demo separata

### Aggiunto
- **Dashboard degli indicatori** (`/indicatori`): MTTR, MTBF, numero e durata dei fermi, arretrato, preventivo/correttivo, costi per macchina, rispetto dei piani, tabella per macchina.
- **`docs/KPI.md`**, con:
  - la formula esatta di ogni indicatore;
  - cosa è incluso o escluso (ordini annullati, fermi aperti, fermi pianificati, macchine dismesse o senza storico);
  - il fuso orario Europe/Rome e l'ora legale;
  - il comportamento ai bordi del periodo;
  - i minimi sotto i quali si mostra "dati insufficienti".
- Periodi rapidi o a scelta, filtri per ubicazione (con sotto-ubicazioni) e categoria; scorciatoia `G K`.
- Test con dati noti e risultati calcolati a mano: ora legale, bordi, fermi aperti, dismesse, filtri.
- **Installazione demo separata:**
  - pacchetto `manutrack-demo-<versione>.zip` (`scripts/release.sh --demo`, `release.ps1 -Demo`, preparato anche dalla CI);
  - comando `php artisan manutrack:demo [--azzera]`;
  - azzeramento ogni notte (`MANUTRACK_DEMO_RESET_AT`, predefinito 03:00): database ricreato, file caricati e sessioni cancellati;
  - banner "Versione dimostrativa" e credenziali di prova nella pagina di accesso.
- **Edizione** (cliente, demo, sviluppo) scritta nel pacchetto (`release.json`) e nel database:
  - l'installazione normale rifiuta il pacchetto demo;
  - demo e azzeramento partono solo se pacchetto, `.env` e database dicono tutti "demo", e mai su un database illeggibile;
  - l'aggiornamento rifiuta pacchetti di un'altra edizione;
  - la pagina Sistema mostra l'edizione e ogni discordanza.
- Sei mesi di storico dimostrativo per gli indicatori.

### Modificato
- L'avviso degli account `@example.com` in produzione si spegne solo in una demo confermata da pacchetto, `.env` e database (non più con il solo `MANUTRACK_DEMO=true`).
- Piano a fasi: la dashboard KPI è la fase 8; notifiche complete, grafici ed esportazioni passano alla fase 9.

### Corretto
- L'invio immediato di una notifica fissava un limite di 60 secondi a tutto il processo PHP e non lo toglieva più. Il resto del lavoro nello stesso processo (cron, generazione dei piani, azzeramento demo) poteva interrompersi. Ora il limite precedente viene ripristinato subito dopo l'invio; test di regressione.

## [0.7.0] - Fase 7: manutenzione preventiva

### Aggiunto
- **Piani preventivi** (`/preventiva`):
  - a tempo (ogni N giorni, settimane, mesi, anni) o a contatore (ogni N ore, cicli…);
  - scadenze a date fisse (senza slittamenti di fine mese) o dall'ultima esecuzione;
  - anticipo in giorni o in valore del contatore;
  - assegnatario, priorità, checklist, durata stimata, ricambi previsti;
  - sospensione, copia su un'altra macchina, eliminazione.
- **Generazione automatica** degli ordini di lavoro preventivi:
  - dal cron ogni 15 minuti, subito dopo una lettura del contatore, o con "Genera ora";
  - al massimo un ordine aperto per piano, anche con esecuzioni contemporanee (riga del piano bloccata; test con processi separati);
  - avviso all'assegnatario, oppure a chi pianifica se il piano non ha assegnatario;
  - comando `php artisan manutrack:preventiva` (con `--prova`).
- **Avanzamento:** al completamento la scadenza successiva; un ordine annullato conta come saltato; un contatore azzerato mantiene quanto mancava alla soglia.
- **Calendario** mensile con ordini generati e scadenze previste (stimate dal consumo medio per i contatori); elenco per giorno sul telefono; "Solo i miei".
- **Rispetto dei piani** negli ultimi 12 mesi: in tempo, in ritardo, saltati, scaduti aperti, percentuale; anche per singolo piano.
- Piani nella scheda macchina, "Dal piano" nella scheda dell'ordine, ricambi previsti proposti per primi nel prelievo, "Preventiva da fare" in panoramica.
- Area "Preventiva" nel registro attività; scorciatoia `G P`.
- Test: calcolo delle scadenze (fine mese, anni bisestili, ritardi), generazione e avanzamento, concorrenza, rispetto dei piani, permessi per ruolo, pagine.
- Piani dimostrativi inventati, creati solo dal seeder di sviluppo.

### Modificato
- Una scadenza indicata come giorno vale fino alla fine di quel giorno: l'ordine risulta scaduto dal giorno dopo.
- Creazione e assegnazione degli ordini accettano il "sistema" come autore (ordini nati dai piani).

### Corretto
- Registro attività: le modifiche ora registrano il valore precedente; prima "prima" e "dopo" erano uguali.

## [0.6.1] - Verifiche dopo la fase 6

### Aggiunto
- Test di concorrenza reale: più processi PHP prelevano o rendono lo stesso ricambio nello stesso istante (MariaDB e MySQL 8 in CI).
- Il pacchetto di rilascio si rifiuta di includere seeder, factory, test o tracce degli account dimostrativi; test relativi.

### Modificato
- Magazzino: il controllo "non più del prelevato" del reso e lo stato dell'ordine nel prelievo si verificano dentro la transazione, a righe bloccate.
- La rettifica scrive movimento e registro attività nella stessa transazione; l'avviso di scorta minima parte dopo la conferma.
- Il pacchetto di rilascio non contiene più `database/seeders` e `database/factories`.

## [0.6.0] - Fase 6: magazzino ricambi

### Aggiunto
- **Ricambi** (`/magazzino`):
  - anagrafica con codice, categoria, unità, costruttore, scorta minima e quantità da riordinare;
  - giacenza per magazzino con lo scaffale;
  - costo medio ponderato;
  - stato Disponibile / Sotto scorta / Esaurito, con filtri e conteggi;
  - scheda con giacenze, fornitori, macchine su cui è montato e ultimi movimenti.
- **Movimenti:**
  - carico (fornitore, DDT, costo), prelievo, reso, rettifica dopo una conta (motivo obbligatorio, nel registro attività), trasferimento tra magazzini;
  - giacenza mai negativa, anche con due prelievi contemporanei (righe bloccate durante il movimento);
  - giacenza risultante registrata a ogni movimento; registro con filtri.
- **Magazzini e fornitori**; un magazzino con giacenza non si disattiva.
- **Scorta minima:** notifica (in coda) a chi gestisce il magazzino quando un ricambio ci scende; pagina **Da riordinare** raggruppata per fornitore preferito, con quantità proposta, "Copia l'elenco" e stampa; card in panoramica.
- **Ricambi nell'ordine di lavoro:**
  - prelievo proponendo prima la distinta della macchina;
  - reso fino a quanto prelevato, al costo di uscita;
  - il costo dei ricambi entra nei costi dell'ordine.
- **Distinta ricambi per macchina**, modificabile dalla scheda del ricambio e visibile nella scheda macchina.
- **Importazione dei ricambi** da CSV o Excel con anteprima, fornitori creati al volo e giacenza iniziale per i ricambi nuovi; modello CSV scaricabile.
- Area "Magazzino" nel registro attività; scorciatoia `G R`.
- Test: servizio delle giacenze (costo medio, giacenza negativa, prelievi con dati vecchi, reso, rettifica, trasferimento, scorta minima), matrice dei permessi per ruolo, pagine e importazione.
- Magazzini, fornitori e ricambi dimostrativi inventati e l'account `magazzino@example.com`, creati solo dal seeder di sviluppo.

### Modificato
- La lettura dei file CSV ed Excel è in comune tra l'importazione delle macchine e quella dei ricambi.
- I costi dell'ordine mostrano i ricambi al posto di "con il magazzino".

## [0.5.1] - Correzioni alla fase 5

### Aggiunto
- **Correggi stato** di un ordine di lavoro, solo per l'amministratore:
  - qualunque stato, anche dopo la chiusura;
  - motivo obbligatorio;
  - registrazione nello storico dell'ordine (segnata "correzione") e nel registro attività;
  - aggiustamento dei fermi macchina collegati (chiusura, riapertura, spostamento con la data di completamento).
  - Gli altri ruoli restano vincolati al ciclo normale.
- Avviso nella pagina Sistema se in produzione esistono account con indirizzi `@example.com`, `.org` o `.net`.
- Test che verificano che `manutrack:install` e l'installazione dal browser non creano account né dati dimostrativi.

### Modificato
- I seeder dimostrativi non creano nulla in produzione, salvo con `MANUTRACK_DEMO=true`.

## [0.5.0] - Fase 5: segnalazioni e ordini di lavoro

### Aggiunto
- **Segnalazione rapida dal telefono** (`/segnala`, pulsante centrale): pochi tocchi.
  - Macchina da QR inquadrato dal vivo, da foto del QR, da ricerca, dalle recenti, oppure "non è una macchina".
  - Cosa succede (con frasi rapide), macchina ferma sì/no, urgenza, foto.
- **Lettore QR nell'app:** `BarcodeDetector` su Android, `jsQR` su iPhone, caricato solo quando serve.
- **Invio differito senza rete:**
  - pagina disponibile offline;
  - segnalazione salvata sul telefono (IndexedDB) e inviata da sola al ritorno della rete (Background Sync su Android, alla riapertura su iPhone);
  - senza doppioni e con l'ora di scrittura;
  - mai cancellata da sola;
  - avviso "in attesa di invio" in tutte le pagine.
- **Notifica urgente immediata** per le segnalazioni critiche e per le macchine di criticità A ferme; le altre in coda.
- **Valutazione delle segnalazioni:** accetta (crea l'ordine di lavoro con assegnatari, scadenza, checklist), respingi con motivo, duplicata; esito notificato a chi ha segnalato.
- **Ordini di lavoro:**
  - numerazione `OdL-AAAA-NNNNN`;
  - tipi e priorità;
  - ciclo di vita completo come da proposta (aperto, pianificato, in corso, in attesa con motivo, completato, chiuso, riaperto, annullato), controllato anche per l'amministratore;
  - storico degli stati.
- "Prendo io", assegnatari con notifica (urgente se critico), checklist da modelli con voci a spunta o a valore, cronometro (uno per persona) e tempi a mano.
- Fermi macchina aperti dalla segnalazione e chiusi al completamento, con macchina di nuovo operativa.
- Codici guasto (problema, causa, rimedio), note di chiusura, foto, commenti, costi (manodopera × costo orario + esterni).
- Elenchi con schede per fase, "Solo i miei", filtri, ordine per urgenza, scaduti; scorciatoie `N`, `F`, `M`, `G E`.
- Impostazioni manutenzione: modelli di checklist, codici guasto, costo orario.
- Panoramica con segnalazioni da valutare e "I miei ordini"; scheda macchina con storico interventi e "Segnala un guasto".
- **Foto in WebP in tutta l'app** (richiesta durante la fase):
  - sul telefono si riducono a 1600 px in WebP, o in JPEG su Safari;
  - sul server WebP qualità 80, 1600 px più miniatura 400 px, senza EXIF né GPS, raddrizzate anche senza l'estensione `exif`;
  - ripiego JPEG segnalato in installazione e nella pagina Sistema;
  - le foto HEIC non convertibili si conservano tra i documenti con un avviso.
- Comando `php artisan manutrack:foto-ottimizza` (con `--prova`) per convertire le foto già salvate, verificandole prima di cancellare gli originali.
- All'uscita si svuotano le pagine salvate sul telefono (`Clear-Site-Data`).
- Test della matrice dei permessi per ogni ruolo; controlli nel browser `scripts/e2e/report-check.cjs`.
- Persone, segnalazioni e ordini dimostrativi inventati.
- Libreria `jsqr` (Apache-2.0).

### Modificato
- Il richiedente trova "Segnalazioni" al posto di "Ordini" nella barra in basso.
- Dal QR stampato, chi può solo segnalare arriva direttamente alla segnalazione di quella macchina.

### Corretto
- Un nome di variabile duplicato avrebbe nascosto lo storico delle letture dei contatori nella scheda macchina (trovato dall'analisi statica prima del rilascio).

## [0.4.0] - Fase 4: macchine e impianti

### Aggiunto
- **Notifiche push urgenti:** il guasto o il fermo di una macchina di criticità A (che ferma la linea) avvisa subito i responsabili.
  - L'invio parte appena l'app ha risposto, senza aspettare il cron, ad alta priorità.
  - Se fallisce per un problema temporaneo, si ritenta dalla coda.
  - Le altre notifiche restano in coda.
  - La pagina Sistema mostra come il server gestisce l'invio immediato.
- **Ubicazioni** a tre livelli (sito › area › linea), con spostamenti che aggiornano tutto il ramo.
- **Categorie** con icona e forma per la futura vista 3D.
- **Macchine:**
  - elenco con ricerca, filtri (anche per ramo dell'ubicazione), conteggi per stato e quattro ordinamenti;
  - scheda con stato, criticità A/B/C, sottogruppi, dati tecnici, garanzia e costo (visibile solo a chi vede i costi);
  - modulo di creazione e modifica;
  - eliminazione protetta.
- **Cambio di stato** dalla scheda, con nota, registro attività e notifiche ai responsabili.
- **Contatori** con letture (numeri all'italiana), controllo dei valori progressivi, sostituzione del contatore e storico.
- **Foto e documenti** su disco privato:
  - si scaricano solo con il permesso;
  - le foto sono raddrizzate e ridimensionate (1600 px più miniatura 400 px), senza metadati né posizione GPS.
- **Codici QR** per macchina (codice casuale rigenerabile) ed **etichette A4** in tre formati, con "salta le prime" per i fogli già usati.
- **Importazione** da Excel (XLSX) o CSV:
  - anteprima con errori per riga;
  - creazione di ubicazioni e categorie mancanti;
  - aggiornamento per codice senza cancellare i dati con le celle vuote;
  - tutto o niente.
- Permessi nuovi: cambiare lo stato, registrare letture, aggiungere foto e documenti (predefiniti per responsabile e tecnico).
- Panoramica: elenco delle macchine ferme o in guasto.
- Macchine dimostrative inventate per lo sviluppo.
- Libreria `openspout/openspout` (MIT) per leggere i file Excel.

### Corretto
- La guida `TEST-DISPOSITIVI.md` consigliava `config:cache` dopo aver generato le chiavi VAPID: ora indica `config:clear`. Con `config:cache` le modifiche successive a `.env` sarebbero state ignorate.

## [0.3.0] - Fase 3: app installabile e notifiche push di prova

### Aggiunto
- App installabile su PC, iPhone e Android:
  - service worker generato da Laravel, con versione legata al rilascio;
  - pagina "Sei offline";
  - schermate di avvio per iPhone in 11 formati.
- Avviso "Nuova versione disponibile": la pagina si ricarica solo quando si tocca "Aggiorna".
- Procedura guidata **App e notifiche** (`/app`) in tre passi (installa, notifiche, prova), con illustrazioni animate per iPhone e Android e istruzioni per riattivare le notifiche negate.
- Il permesso alle notifiche si chiede solo dopo un tocco, mai all'apertura della pagina.
- Notifiche push:
  - iscrizione per dispositivo;
  - "Invia notifica di prova";
  - numero sul pallino dell'icona dove supportato;
  - rinnovo automatico dell'iscrizione;
  - eliminazione delle iscrizioni rifiutate o fallite 5 volte di seguito.
- Pagina **Diagnostica notifiche** (`/notifiche/diagnostica`): controlli su HTTPS, service worker, installazione, versione di iOS, permesso, iscrizione e chiavi; prova su uno o tutti i propri dispositivi; "Copia rapporto"; elenco dei dispositivi.
- Invito all'installazione nella panoramica, su telefono.
- Guida [`docs/TEST-DISPOSITIVI.md`](docs/TEST-DISPOSITIVI.md):
  - sottodominio con HTTPS su DirectAdmin;
  - chiavi VAPID anche senza SSH;
  - checklist per iPhone e Android reali.
- Controllo automatico nel browser `scripts/e2e/pwa-check.cjs`.

### Corretto
- Le notifiche push fallivano sui server senza le estensioni `bcmath` o `gmp`: un avviso della libreria veniva trattato come errore. Ora funzionano anche senza (le estensioni restano consigliate per la velocità).

## [0.2.0] - Fase 2: accesso, ruoli e impostazioni dell'azienda

### Aggiunto
- Accesso con Laravel Fortify: login con limite di tentativi, password dimenticata, verifica in due passaggi (app di autenticazione e codici di recupero), conferma della password per le aree sensibili. Niente registrazione libera.
- Sei ruoli dell'azienda (amministratore, responsabile manutenzione, tecnico, magazziniere, richiedente, osservatore) con permessi per modulo e una matrice ruoli × permessi modificabile dall'amministratore. A ogni aggiornamento ruoli e permessi vengono riallineati senza perdere le modifiche fatte.
- Persone: elenco con ricerca e filtri, inviti via email con link valido 7 giorni (nel database c'è solo l'impronta del codice), cambio ruolo, disattivazione e riattivazione, azzeramento della verifica in due passaggi, invio del link per una nuova password. Resta sempre almeno un amministratore attivo.
- Impostazioni dell'azienda: nome, fuso orario, lingua, logo del cliente (versione principale e versione facoltativa per il tema scuro), palette tra le quattro preimpostazioni o personalizzata con contrasti verificati, proposta dei colori presi dal logo, icone dell'app rigenerate per la palette personalizzata.
- Palette **Pistacchio** (quarta preimpostazione, valori provvisori).
- Intestazione con il logo del cliente; ManuTrack resta in piccolo nella pagina di accesso e nel piè di pagina.
- Profilo personale: dati, password, verifica in due passaggi, tema.
- Registro delle attività (accessi, persone, ruoli, impostazioni, sistema) con ricerca; non salva mai password o segreti.
- Pagina Sistema riservata agli amministratori, con conferma della password e pulsanti "Applica ora", "Fai ritentare al cron" e "Crea una copia ora". L'avviso sugli aggiornamenti compare solo a chi gestisce il sistema.
- Lingua e fuso orario dell'azienda applicati a ogni pagina.

### Modificato
- Lo stato "operativa" passa a un verde acqua, per distinguerlo dagli accenti verdi.
- Nuovi token `--mt-accent-fg` (testi d'accento) e `--mt-accent-soft` (aloni ed evidenziazioni).
- Tutte le pagine dell'app richiedono l'accesso.

### Corretto
- La palette scelta non veniva applicata quando era diversa da Grafite e Rame: lo stile con i colori dell'azienda veniva caricato prima del CSS principale.
- Prima dell'installazione le pagine portano all'installazione anziché alla pagina di accesso.

## [0.1.0] - Fase 1: fondamenta

### Aggiunto
- Base Laravel 12 con Livewire 3, Alpine.js, Tailwind CSS 4 e Vite.
- Design system "Grafite e Rame" con tema scuro e chiaro, preimpostazioni "Acciaio e Ciano" e "Ardesia e Indaco", componenti Blade riutilizzabili e documentazione in `docs/DESIGN.md`.
- Logo vettoriale ricavato dall'originale, con colori governati da variabili CSS e varianti (completo, solo M, orizzontale, monocromatico chiaro e scuro).
- Icone dell'app, favicon e apple-touch-icon generate dal logo (`php artisan manutrack:icons`).
- Struttura dell'interfaccia per PC (barra laterale) e telefono (barra in basso, aree sicure, unità dvh).
- Installazione da riga di comando (`manutrack:install`) e dal browser (`/install`, protetta da codice, utilizzabile una sola volta).
- Verifica dei requisiti del server, comprese GD o Imagick.
- Aggiornamento ripetibile (`manutrack:update`), anche automatico dal cron solo su pacchetti completi, con blocco contro le esecuzioni doppie.
- Copie del database in PHP prima di ogni aggiornamento (`manutrack:backup`, `manutrack:restore`), con conservazione delle ultime copie.
- Generazione delle chiavi push (`manutrack:vapid`).
- Script per preparare il pacchetto di rilascio su Linux, macOS e Windows.
- Testi dell'interfaccia in italiano in `lang/it`.
