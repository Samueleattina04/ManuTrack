# Indicatori (KPI) — definizioni

Questo documento è la definizione ufficiale di ogni indicatore della dashboard (`/indicatori`).
Il codice (`app/Support/Kpi/KpiCalculator.php`) segue queste formule alla lettera.
I test (`tests/Feature/Kpi/KpiCalculatorTest.php` e, per l'orario di lavoro, `tests/Feature/Kpi/WorkTimeTest.php`) le verificano con dati noti e risultati calcolati a mano.

## 1. Regole comuni

### 1.1 Fuso orario
- Tutte le date e le ore sono nel fuso dell'applicazione: **Europe/Rome** (`APP_TIMEZONE`), con l'ora legale.
- Le date scritte nel database sono già in quel fuso.
- Le **durate si calcolano sul tempo reale trascorso**:
  - il giorno del passaggio all'ora legale dura 23 ore;
  - quello del ritorno all'ora solare dura 25 ore.
- **Limite:** un orario scritto proprio nell'ora ripetuta (tra le 2:00 e le 3:00 dell'ultima domenica di ottobre) è ambiguo e viene letto come la prima delle due. L'errore massimo è di un'ora su quel singolo evento.

### 1.2 Periodo selezionato
- Il periodo va **dal giorno "dal" al giorno "al", estremi compresi**: i giorni sono interi, in ora locale.
- In pratica è l'intervallo **semiaperto** `[inizio, fine)`:
  - `inizio` = "dal" alle 00:00:00;
  - `fine` = il giorno dopo "al" alle 00:00:00.
- **Periodo che finisce nel futuro:** `fine` viene tagliata ad **adesso**. "Mese corrente" vale quindi dal 1° del mese fino a questo istante.
- Un periodo che comincia nel futuro non è valido.
- Periodi rapidi: ultimi 30 giorni (oggi compreso), mese corrente, mese precedente, ultimi 3 mesi, anno corrente, ultimi 12 mesi; oppure date a scelta.
- **Eventi a cavallo dei bordi**, regola per ogni indicatore:

| Indicatore | Un evento conta nel periodo se… | La sua durata conta… |
|---|---|---|
| Numero e durata dei fermi | si sovrappone al periodo | solo la parte dentro il periodo |
| MTTR | il fermo **finisce** nel periodo | per intero, anche la parte prima dell'inizio |
| MTBF: guasti | il guasto **comincia** nel periodo | — |
| MTBF: tempo di funzionamento | — | solo dentro il periodo |
| Preventivo/correttivo, costi | l'ordine è **completato** nel periodo | — |
| Rispetto dei piani | la **scadenza prevista** cade nel periodo (fino a oggi) | — |
| Arretrato | non dipende dal periodo: è la situazione di adesso | — |

### 1.3 Filtri
- **Ubicazione:** comprende anche le sotto-ubicazioni.
- **Categoria di macchina.**
- I filtri valgono per macchine, fermi, ordini e piani.
- Gli **ordini senza macchina**:
  - entrano solo senza filtri;
  - nei costi compaiono come riga "Senza macchina".

### 1.4 Macchine considerate
- **Macchine dismesse:**
  - escluse dall'MTBF, perché il loro tempo di osservazione non è affidabile (non si conosce la data di dismissione);
  - incluse in fermi, MTTR e costi se hanno eventi nel periodo.
- **Inizio dello storico di una macchina:**
  - la data di installazione alle 00:00, se indicata;
  - altrimenti il momento in cui è stata inserita in ManuTrack.
  - Prima di questo istante la macchina non è osservata.
- **Macchine senza storico nel periodo** (inserite o installate dopo la fine): escluse dall'MTBF; non hanno eventi negli altri indicatori.

### 1.5 Guasti e fermi pianificati
- Un **fermo** è un intervallo in cui la macchina è ferma (`downtime_events`). Lo si apre da una segnalazione "macchina ferma" o dall'ordine di lavoro.
- È un **fermo pianificato** se è collegato a un ordine di tipo *preventivo* o *miglioramento*.
- Tutti gli altri fermi sono **fermi per guasto**: collegati a un ordine correttivo, a una segnalazione o a nient'altro.
- **Fermo aperto** (senza fine): per la durata nel periodo vale fino ad adesso (o fino alla fine del periodo); è escluso dall'MTTR.

### 1.6 Dati insufficienti
Quando i dati non bastano per un numero affidabile, la dashboard mostra **"dati insufficienti"** e spiega cosa manca, invece di un numero fuorviante.

| Indicatore | Minimo per mostrare il valore |
|---|---|
| MTTR | 3 fermi per guasto conclusi nel periodo |
| MTBF | 3 guasti nel periodo e almeno 7 giorni-macchina di osservazione (somma delle finestre di osservazione di tutte le macchine considerate) |
| MTBF sulle ore di lavoro (§2.9) | 3 guasti nel periodo e almeno 40 ore di lavoro previste nelle finestre di osservazione |
| Preventivo/correttivo | 5 ordini preventivi + correttivi completati nel periodo |
| Rispetto dei piani | 3 interventi dovuti nel periodo |
| Fermi, arretrato, costi | nessun minimo: sono conteggi e somme (zero è un valore vero) |

- Gli stessi minimi valgono **per singola macchina** nella tabella per macchina.
- Accanto a ogni valore si vede su quanti eventi è calcolato (es. "su 7 fermi").

### 1.7 Arrotondamenti
- Ore con 1 decimale; percentuali con 1 decimale; euro con 2 decimali.
- I calcoli usano i valori pieni: si arrotonda solo alla fine.

## 2. Indicatori

### 2.1 Numero e durata dei fermi
- **Numero:** fermi delle macchine considerate (dismesse comprese) che si sovrappongono al periodo:
  `inizio_fermo < fine` e (`fine_fermo` vuota oppure `fine_fermo > inizio`).
- **Durata (ore):** `Σ ( min(fine_fermo ?? adesso, fine) − max(inizio_fermo, inizio) )`.
- Sono mostrati separati in **per guasto** e **pianificati**, più il numero di fermi ancora **aperti**.

### 2.2 MTTR — tempo medio di ripristino
- **Formula:** `MTTR = Σ durata(f) / n`, con:
  - `f` = fermi **per guasto**, **conclusi**, con `fine_fermo` nel periodo `[inizio, fine)`;
  - `durata(f)` = `fine_fermo − inizio_fermo`, per intero;
  - `n` = numero di questi fermi.
- **Esclusi:** fermi aperti, fermi pianificati.
- Misura il tempo da "macchina ferma" a "macchina ripartita" (il fermo), non il tempo di lavoro registrato sugli ordini.

### 2.3 MTBF — tempo medio tra i guasti
- **Formula:** `MTBF = Σ_m funzionamento(m) / Σ_m guasti(m)`, sulle macchine considerate non dismesse.
- **Finestra di osservazione della macchina m:** `[max(inizio, inizio_storico(m)), fine)`. Se è vuota, la macchina è esclusa.
- **funzionamento(m)** = durata della finestra − durata dei fermi di m dentro la finestra.
  - Tutti i fermi, anche quelli pianificati: in quel tempo la macchina non lavora.
  - Il tempo è di **calendario, 24 ore su 24**. Se l'azienda ha impostato l'orario di lavoro, accanto c'è l'MTBF sulle ore di lavoro (§2.9), che diventa il valore principale.
- **guasti(m)** = fermi **per guasto** di m che **cominciano** nella finestra.
- **Nessun guasto nel periodo:** l'MTBF non è calcolabile e si mostra "nessun guasto" con i giorni-macchina di osservazione. Non si inventa un valore "infinito".
- È un MTBF **di flotta**: le macchine senza guasti contribuiscono con il loro tempo di funzionamento. Nella tabella per macchina ogni macchina ha il suo MTBF, con gli stessi minimi.

### 2.4 Arretrato
- **Istantanea di adesso**, indipendente dal periodo; i filtri valgono.
- **Ordini aperti** = ordini negli stati aperto, pianificato, in corso, in attesa.
- **Di cui scaduti** = con scadenza passata (la scadenza vale fino alla fine del giorno indicato).
- **Età media (giorni)** = media di `(adesso − creazione)` degli ordini aperti.
- Suddivisione per priorità e per tipo.
- **Esclusi:** ordini completati, chiusi, annullati.

### 2.5 Percentuale preventivo / correttivo
- **Ordini contati:** completati nel periodo, cioè:
  - `data di completamento` in `[inizio, fine)`;
  - stato completato o chiuso.
- **Formule:**
  - `% preventivo = preventivi / (preventivi + correttivi) × 100`;
  - `% correttivo = 100 − % preventivo`.
- **Esclusi:**
  - ordini annullati;
  - ordini riaperti e non ancora ricompletati (non hanno una data di completamento);
  - gli ordini di *miglioramento* non entrano nel rapporto: si mostrano a parte.

### 2.6 Costi per macchina
- **Ordini contati:** gli stessi del §2.5, compresi i miglioramenti.
- **Costo di un ordine** = manodopera + ricambi + esterni:
  - **manodopera** = minuti registrati / 60 × costo orario. Il costo orario è quello fissato alla chiusura dell'ordine; se non è chiuso, quello attuale delle impostazioni.
  - **ricambi** = prelievi meno resi, ciascuno al costo a cui è uscito o rientrato dal magazzino.
  - **esterni** = costi esterni scritti sull'ordine.
- Somma per macchina, con il totale e la riga "Senza macchina" (solo senza filtri).
- **Esclusi:** ordini annullati. Eventuali ricambi prelevati per un ordine annullato e non resi restano visibili nei movimenti di magazzino.
- **Visibilità:** i costi si vedono solo con il permesso "Vedere i costi".

### 2.7 Rispetto dei piani
- **Interventi contati:** ordini preventivi generati dai piani con **scadenza prevista** in `[dal, min(al, oggi)]`, in date.
- **Esito:**
  - *in tempo* = completato entro la fine del giorno di scadenza;
  - *in ritardo* = completato dopo;
  - *saltato* = annullato;
  - *scaduto aperto* = non completato, con scadenza passata.
- **Formula:** `Rispetto = in tempo / (in tempo + in ritardo + saltati + scaduti aperti) × 100`.
- Gli interventi non ancora scaduti né completati non contano.
- È la stessa regola della pagina Preventiva (`docs/SPECIFICA.md` §36.5).

### 2.8 Andamento nel tempo (grafici ed esportazione)
- Il periodo è diviso in intervalli: **settimane** (da lunedì a domenica) se dura fino a 100 giorni, altrimenti **mesi** di calendario.
- Il primo e l'ultimo intervallo sono tagliati ai bordi del periodo; gli intervalli che cominciano nel futuro non ci sono.
- **Ogni intervallo è un periodo a sé**, calcolato con le regole di questo documento: un fermo a cavallo di due settimane conta in entrambe per la sua parte; l'MTTR di un intervallo conta i fermi che finiscono lì.
- Valori per intervallo: numero e ore di fermo (§2.1), MTTR con lo stesso minimo di 3 fermi (§2.2), ordini preventivi e correttivi completati (§2.5).
- **MTTR insufficiente** in un intervallo: il grafico interrompe la linea e la tabella scrive "insufficienti"; non si disegna uno zero.
- La somma delle ore di fermo degli intervalli è uguale a quella del periodo intero, a meno degli arrotondamenti a un decimale.

### 2.9 MTBF sulle ore di lavoro
Vale quando almeno un calendario di lavoro è assegnato (*Impostazioni › Orario di lavoro*).

- **Calendario di una macchina in un giorno:** quello assegnato al livello più specifico con "valido dal" non successivo a quel giorno: la macchina (previsto dal calcolo, non dalle pagine), poi la sua ubicazione e quelle che la contengono, dalla più vicina, poi l'azienda. Un'assegnazione vuota eredita dal livello sopra. Senza nessun calendario: 24 ore su 24.
- **Ore di lavoro di un giorno**, nell'ordine:
  1. l'eccezione di quel giorno: *chiuso* (festività, chiusura, patrono) = nessuna ora; *orario speciale* = quelle ore; *si lavora* (festività tolta) = orario normale;
  2. altrimenti i turni del giorno della settimana nella versione dell'orario in vigore quel giorno (la versione con il "valido dal" più recente non successivo).
- **Turni di notte:** un turno appartiene al giorno in cui comincia; se la fine non viene dopo l'inizio finisce il giorno dopo (22:00–06:00), anche se il giorno dopo è festa; 00:00–00:00 = tutto il giorno. Per questo si guarda anche il giorno prima dell'inizio della finestra: il turno di sabato notte conta per la domenica 1°.
- **Ora legale:** le ore sono tempo reale trascorso (§1.1): il turno 22:00–06:00 della notte del passaggio all'ora legale dura 7 ore, quello del ritorno all'ora solare 9; 00:00–00:00 dura 23 o 25 ore in quei giorni.
- **Formule**, sulla stessa finestra di osservazione del §2.3 per ogni macchina m:
  - `lavoro(m)` = durata degli intervalli di lavoro di m dentro la finestra (nei giorni senza calendario: 24 ore);
  - `fermo_in_lavoro(m)` = parte dei fermi di m (tutti, anche pianificati) che cade dentro quegli intervalli;
  - `MTBF lavoro = Σ_m (lavoro(m) − fermo_in_lavoro(m)) / Σ_m guasti(m)`;
  - **guasti** = gli stessi del §2.3, **anche se cominciano fuori orario** (trovato fermo il lunedì mattina, guasto di domenica, giorno chiuso): il guasto c'è stato comunque.
- **Base del valore:** *lavoro* se tutte le macchine considerate hanno un calendario per tutto il periodo; *misto* se alcune macchine o alcuni giorni sono senza calendario (contano 24 ore su 24). La dashboard, il rapporto e l'esportazione lo scrivono.
- **Come si mostra:** l'MTBF sulle ore di lavoro è il valore **principale**; l'MTBF sulle 24 ore resta sotto come valore secondario, con l'etichetta "24 ore su 24". Nella tabella per macchina, per le macchine con un calendario.
- **Mesi passati invariati:** ogni orario settimanale e ogni assegnazione ha il suo "valido dal". Un orario già in vigore non si modifica (si crea un orario nuovo da oggi o da una data futura; il giorno della creazione si può ancora correggere); i giorni speciali passati non si modificano; un calendario già assegnato non si elimina. Un'assegnazione con una data passata è una scelta esplicita e la pagina avvisa che cambia gli indicatori da quella data.
- **Festività:** le festività nazionali italiane (Capodanno, Epifania, Pasqua, Lunedì dell'Angelo, 25 aprile, 1° maggio, 2 giugno, Ferragosto, Ognissanti, 8 dicembre, Natale, Santo Stefano; dal 2026 anche il 4 ottobre, San Francesco d'Assisi) sono proposte in automatico per l'anno in corso e il successivo (alla creazione del calendario e ogni notte) e si possono togliere o rimettere; chiusure aziendali e santo patrono si inseriscono a mano.
- **Escluso per ora:** MTTR sulle ore di lavoro e disponibilità (lavoro − fermi) / lavoro: si possono aggiungere con gli stessi dati.

## 3. Permessi
- La dashboard richiede "Vedere i report" (amministratore, responsabile, magazziniere, osservatore).
- I costi richiedono anche "Vedere i costi".
- Esportazione degli indicatori: "Esportare CSV ed Excel" più "Vedere i report"; il foglio dei costi solo con "Vedere i costi".
- Rapporto mensile (`docs/SPECIFICA.md` §43): "Vedere i report"; usa queste stesse formule sul mese di calendario intero.

## 4. Orario di lavoro

Senza calendari assegnati l'MTBF resta solo sulle 24 ore (§2.3). Con un calendario si applica il §2.9; la decisione e le alternative scartate sono in `docs/PROPOSTA.md`, "Proposta: orario di lavoro".
