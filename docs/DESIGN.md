# Design system di ManuTrack

Riferimento per colori, caratteri, spaziature, componenti e icone. La versione dal vivo è la pagina **Design system** dell'app (`/design`). L'anteprima approvata è in `docs/anteprima/stile.html`.

## Principi

1. **Il colore forte è riservato allo stato.** Come nelle interfacce HMI ad alte prestazioni usate in fabbrica, i fondi sono neutri e calmi. Il colore pieno segnala ciò che richiede attenzione: guasto, scadenza, macchina ferma.
2. **Mai solo colore.** Ogni stato ha colore + icona + testo. Si legge anche con i guanti, sotto il sole, da chi non distingue i colori o con lo schermo in scala di grigi.
3. **Telefono e PC hanno compiti diversi:**
   - telefono: una mano, bersagli grandi, poche scelte a pulsanti;
   - PC: tabelle dense, filtri, scorciatoie, più colonne.
4. **Il 3D ha uno scopo:** logo d'ingresso, pianta dello stabilimento, anteprima della macchina. Le schermate operative restano leggere. Il 3D si carica solo dove serve e ha sempre una versione 2D di riserva.
5. **Movimento misurato.** Transizioni brevi (150–350 ms), nessuna animazione che blocchi un'azione. Con `prefers-reduced-motion` il movimento si ferma.

## Colori

I token sono variabili CSS in `resources/css/app.css`.
- **Tema scuro (predefinito):** valori su `:root`.
- **Tema chiaro:** valori su `:root[data-theme="light"]`.

Il tema si sceglie tra *Scuro*, *Chiaro* e *Come il dispositivo* e resta salvato sul dispositivo. Le classi Tailwind usano gli stessi token: `bg-surface`, `text-muted`, `border-line`, `text-ok`, ecc.

### Neutri (grafite con una punta di blu)

| Token | Scuro | Chiaro | Uso |
|---|---|---|---|
| `--mt-bg` | `#0C0F14` | `#EDEFF2` | fondo della pagina |
| `--mt-bg-2` | `#10141A` | `#F4F5F7` | fondo dei campi |
| `--mt-surface` | `#151A21` | `#FFFFFF` | schede, barra laterale |
| `--mt-surface-2` | `#1C222B` | `#F5F6F8` | elementi rialzati, righe evidenziate |
| `--mt-line` | `#29303B` | `#D6DAE0` | bordi |
| `--mt-fg` | `#E8EBF0` | `#10141A` | testo |
| `--mt-fg-2` | `#B6BDC8` | `#39414D` | testo secondario |
| `--mt-muted` | `#8C95A3` | `#5B6472` | etichette e note (AA su fondo) |

### Accento (dalla palette dell'azienda)

La palette si sceglie in installazione e, dalla fase 2, nelle impostazioni. Le variabili `--acc-*` e `--logo-l*` vengono scritte nella pagina da `<x-head>` (vedi `App\Support\Brand\Brand::cssVariables()`).

| Palette | Accento scuro | Accento chiaro | Faccia chiara M | Faccia scura M |
|---|---|---|---|---|
| **Grafite e Rame** (predefinita) | `#E8915A` | `#A64B17` | `#EE9455` / `#DD7536` | `#5E2910` / `#33190D` |
| Acciaio e Ciano | `#3FCBE4` | `#0A6C80` | `#3CC6E0` / `#169FB9` | `#0C3B47` / `#0A2830` |
| Ardesia e Indaco | `#9C96FF` | `#4B3FCF` | `#9089FF` / `#6A5EEE` | `#28226B` / `#1C184A` |
| Pistacchio (provvisoria) | `#3A7D3F` | `#2A5F2F` | `#A8CB6E` / `#8DB451` | `#24502A` / `#1E4224` |
| Personalizzata | calcolata da `PaletteBuilder` partendo dal colore principale e da quello chiaro | | | |

Oltre all'accento dei pulsanti, la palette definisce due colori:
- **`--mt-accent-fg`** per testi e icone d'accento sul fondo (Pistacchio: `#B5D67F` nel tema scuro, 11,8:1);
- **`--mt-accent-soft`** per aloni ed evidenziazioni.

Se non sono indicati, coincidono con l'accento.

Il testo sopra l'accento usa `--mt-on-accent`: quasi nero nel tema scuro, bianco nel tema chiaro.

### Stati (indipendenti dalla palette)

| Token | Scuro | Chiaro | Significato | Icona |
|---|---|---|---|---|
| `--mt-ok` | `#2FD3B0` | `#0A7563` | operativa, completato (verde acqua: distinto dagli accenti verdi) | `check-circle` |
| `--mt-ko` | `#FF6B6F` | `#BF1F2A` | guasto, critico, scaduto | `alert-triangle` |
| `--mt-mnt` | `#6AA6FF` | `#1A5BCC` | in manutenzione (blu, come le lampade andon) | `wrench` |
| `--mt-stop` | `#A5AEBB` | `#556070` | ferma, bassa priorità | `pause-circle` |
| `--mt-warn` | `#F2C14E` | `#7D5C00` | scadenza vicina, in attesa | `clock` |

Tutti i colori di stato hanno contrasto **AA (≥ 4,5:1)** sul proprio fondo, sia da soli sia dentro le etichette di stato.

## Logo del cliente

Il logo dell'azienda cliente non è nel codice: lo carica l'amministratore. Il componente `<x-company-logo size="sm|md|lg" />`:
- mostra la versione adatta al tema (`company-logo-light` / `company-logo-dark`);
- se manca la versione scura, nel tema scuro appoggia il logo su una targhetta chiara (`company-logo-plate`);
- se non c'è nessun logo, mostra la M e il nome dell'azienda.

`<x-powered-by :version="true" />` mostra "con ManuTrack" in piccolo.

## Caratteri

Tutti con licenza SIL OFL, ospitati dall'app tramite `@fontsource`. Nessuna richiesta a Google Fonts.

| Ruolo | Carattere | Pesi | Note |
|---|---|---|---|
| Titoli (`font-display`, `h1–h3`) | **Archivo** variabile | 600–800 | larghezza 112–115% (`font-stretch`) |
| Testo e interfaccia (`font-sans`) | **IBM Plex Sans** | 400, 500, 600 | corpo 15 px; **16 px nei campi**, per evitare lo zoom su iPhone |
| Codici e numeri (`font-mono`, `.code`) | **IBM Plex Mono** | 500 | codici macchina, numeri d'ordine, versioni |

I numeri in colonna usano `.num` (`tabular-nums`). Le etichette in maiuscolo (`.eyebrow`) hanno spaziatura 0,12 em.

## Spaziature, angoli, ombre

- Scala di Tailwind (multipli di 4 px). Tra le schede si usa `gap-3` (12 px), tra le sezioni `gap-6` (24 px), dentro le schede `p-4`/`p-5`.
- Angoli: `--radius-md` 12 px (campi, pulsanti), `--radius-lg` 14 px (schede), `--radius-xl` 22 px (pannelli grandi).
- Ombre: `--mt-shadow` (schede), `--mt-shadow-pop` (in rilievo, inclinazione 3D).
- Vetro smerigliato (`.glass`) solo dove migliora la leggibilità sopra contenuti che scorrono: barra superiore, barra in basso, finestre.

## Struttura delle pagine

| | PC (≥ 1024 px) | Telefono |
|---|---|---|
| Navigazione | barra laterale fissa (15,5 rem) con scorciatoie | barra in basso con 4 voci + pulsante centrale "Segnala" |
| Barra superiore | titolo, ricerca `Ctrl K` | logo, titolo, ricerca |
| Contenuto | fino a 6xl, più colonne | una colonna |

- **Aree sicure:** le variabili `--safe-top/bottom/left/right` (da `env(safe-area-inset-*)`) proteggono notch e barra Home. Il meta viewport usa `viewport-fit=cover`.
- **Altezze dinamiche:** `min-height: 100dvh`; nessun `100vh` nelle strutture principali.
- **Niente rimbalzo:** `overscroll-behavior-y: none` sul body.

## Componenti

| Componente | Uso | Note |
|---|---|---|
| `<x-logo variant="full|mark|horizontal" :shadow="true">` | marchio | colori da `--logo-faccia-chiara`, `--logo-faccia-scura`, `--logo-ombra`, `--logo-testo`; classi `logo-mono-light`, `logo-mono-dark`, `logo-on-dark`, `logo-on-light` |
| `<x-icon name="wrench" />` | icona Lucide | 20 px, tratto 2; `label="…"` per renderla leggibile dagli screen reader |
| `<x-status :status="$enum" />` o `<x-status tone="ok" icon="check-circle">Testo</x-status>` | stato | sempre icona + testo |
| `<x-priority :priority="$enum" />` | priorità | 1–4 barre + testo |
| `.btn` + `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-danger`, `.btn-xl`, `.btn-sm`, `.btn-icon` | pulsanti | altezza minima **44 px**; **56 px** (`.btn-xl`) per le azioni da campo |
| `<x-form.input name label hint>` | campo con etichetta, aiuto ed errore | `aria-invalid` e `aria-describedby` automatici |
| `.card`, `.alert(-ok/-warn/-ko)`, `.kbd`, `.table`, `.eyebrow` | superfici, avvisi, tasti, tabelle dense, sopratitoli | |
| `<x-theme-switcher />` | scelta del tema | |
| `x-tilt` (Alpine) | inclinazione 3D al passaggio del mouse | solo puntatori fini, disattivata con movimento ridotto |
| `x-count-up` (Alpine) | numero che si anima | il valore finale è già nel markup |
| `<x-criticality :value="$enum" />` | criticità A/B/C | lettera in un quadratino del colore di stato (A rosso, B ambra, C grigio) e testo completo per gli screen reader |
| `<x-asset-thumb :asset="$a" />` | miniatura della macchina | foto principale (400 px) oppure l'icona della categoria |
| `.status-filter` (+ `.is-active`, `--c`) | pastiglie di filtro con conteggio | altezza 40 px; sul telefono scorrono in orizzontale |
| `.choice` | scelta a schede (radio nascosto) | bordo d'accento sulla scheda scelta; usata per criticità, stato della macchina e icone |
| `.tree-children` | rami dell'albero delle ubicazioni | rientro con filo verticale |

## Logo

- **Originale:** `public/images/logo-originale.png`, non modificato.
- **Geometria:** ricalcata dall'originale in `App\Support\Brand\Brand`, in unità del file 1200×1200 con origine nell'angolo della M:
  - faccia chiara `0,0 203,202 203,339 90,227 86,377 0,292`;
  - faccia scura speculare;
  - ombra portata di 90 unità in diagonale.
- **Scritta:** Open Sans Bold convertita in tracciati (`npm run brand` → `resources/brand/wordmark.json`). Ha la stessa larghezza (780 unità) e la stessa altezza delle maiuscole (107) dell'originale, così il logo non dipende dai font installati.
- **Effetto 3D:** due toni della stessa famiglia, una sfumatura scura in alto sulla faccia chiara (come nell'originale), un riflesso leggero e l'ombra.
- **Varianti:** completa, solo M, orizzontale, monocromatica chiara e scura, su fondo scuro e su fondo chiaro.
- **Icone dell'app:** `php artisan manutrack:icons` le disegna in PHP (GD) per ogni palette in `public/icons/<palette>/`:
  - `icon-192/512`, `maskable-192/512` con la M dentro il cerchio sicuro all'80%;
  - `apple-touch-icon` 180;
  - `favicon-32`, `favicon.svg`.
  Lo sfondo è pieno, grafite con un alone del colore della palette.
- **Schermate di avvio iPhone:** fondo `#0C0F14` con la M al centro (34% della larghezza), 11 formati per palette (`splash-<larghezza>x<altezza>.png`).

## Macchine

- **Elenco:** tabella densa su PC; sul telefono schede con miniatura, codice, ubicazione, stato e criticità, con la ricerca sempre visibile e gli altri filtri dietro "Filtri" (pallino con il numero di filtri attivi).
- **Scheda:** in alto la foto (sul telefono una miniatura quadrata accanto al nome), poi il cambio di stato con schede grandi da toccare; le macchine A mostrano un avviso rosso "notifica urgente" prima della conferma. Contatori con il valore in grande e il campo della lettura con l'unità dentro (`inputmode="decimal"`).
- **Etichette QR:** pagina di stampa a sé, sempre chiara, misure in millimetri, codice in IBM Plex Mono; tratteggio di taglio solo a schermo.

## Segnalazioni e ordini

- **Segnalazione rapida:**
  - una colonna, pulsanti da 56 px;
  - il primo gesto è "Inquadra il QR", su tutta la larghezza;
  - lettore a tutto schermo con riquadro bianco e fondo oscurato, e due pulsanti grandi in basso ("Usa una foto", "Annulla") sopra la barra Home dell'iPhone;
  - "La macchina è ferma?" come due pulsanti grandi (rosso / accento);
  - urgenza come quattro schede con le barre di priorità;
  - pulsante d'invio fisso sopra la barra in basso;
  - stato senza rete sempre visibile (avviso giallo, testo del pulsante che cambia).
- **Avviso della coda offline:** striscia gialla sotto la barra superiore in tutte le pagine; diventa verde per qualche secondo dopo l'invio.
- **Elenchi:** a sinistra una barra rossa per le segnalazioni urgenti e le barre di priorità per gli ordini; a destra lo stato, sempre con icona e testo; schede di stato a pastiglia con i conteggi.
- **Ordine di lavoro:**
  - azioni del ciclo di vita come pulsanti grandi, quella principale piena;
  - i passaggi che chiedono dati (attesa, completamento, annullamento, riapertura) aprono un riquadro sotto i pulsanti;
  - checklist con caselle da 24 px e barra di avanzamento verde;
  - cronometro con il tempo che scorre nel pulsante "Ferma".

## Indicatori

- **Quattro riquadri** in alto (MTTR, MTBF, fermi, arretrato): valore grande in cifre tabellari con l'unità in grigio; sotto, su quanti eventi è calcolato.
- **"Dati insufficienti"** in grigio al posto del numero, con la frase che spiega cosa manca: mai un numero fuorviante, mai uno zero inventato.
- **Preventivo/correttivo:** barra orizzontale a due colori (verde preventivo, ambra correttivo) con le percentuali scritte accanto, mai solo il colore.
- **Periodo** con le pastiglie dei periodi rapidi; sotto, in piccolo, l'intervallo esatto con il fuso orario.
- In fondo "Come si calcolano", in parole semplici; le definizioni complete sono in `docs/KPI.md`.

### Grafici
- **Sobri:** niente ombre, sfumature o 3D. Barre con angoli di 4 px, linee di 2 px con punti di 3 px; griglia orizzontale leggera, assi senza linea.
- **Colori dai token del tema** (letti dalle variabili CSS, quindi giusti in chiaro e in scuro): fermi in rosso tenue, MTTR con il colore d'accento, preventivo verde e correttivo ambra come nella barra, costi impilati in tre toni.
- **Sul telefono:** altezza fissa (14 rem), al massimo 6 etichette sull'asse orizzontale con caratteri più piccoli; il tocco mostra il valore.
- **Sempre una tabella equivalente** sotto ogni grafico ("Mostra i dati in tabella") e una frase che riassume il grafico per i lettori di schermo. In stampa resta solo la tabella.
- Animazione breve (500 ms) all'apertura; nessuna con "riduci movimento".

### Esportazioni e rapporto
- **Esporta** è un pulsante secondario con menu (Excel o CSV) accanto alle altre azioni dell'elenco; dice in una riga che esporta i filtri applicati, tutte le pagine.
- **Rapporto mensile:** pagina chiara a sé, come le etichette QR: barra in alto (indietro, mesi, "Stampa o salva in PDF") che sparisce in stampa; foglio A4 con logo del cliente, riquadri degli indicatori e tabelle con intestazione ripetuta su ogni pagina. Sul telefono il foglio occupa tutta la larghezza e le tabelle scorrono di lato.

## 3D

- **Facce piane come il logo:** forme semplici a pochi poligoni, ombreggiatura piatta, nessuna texture. Una tinta per macchina (il colore dello stato) e la stessa tinta più scura per i dettagli.
- **Colori dai token** (`--mt-ok`, `--mt-ko`, `--mt-mnt`, `--mt-stop`, `--mt-surface-2`, `--mt-accent-soft`): cambiano con il tema chiaro o scuro e con la palette, senza ricaricare.
- **Stato mai solo con il colore:** nel 3D c'è il pannello con il testo, nel 2D un simbolo (✓ ! ⚙ ■) accanto al codice; elenco con le etichette di stato sotto la vista.
- **Vista dello stabilimento:** inquadratura dall'alto in diagonale che contiene tutto il pavimento; griglia di 1 m (più marcata ogni 5 m); zone tratteggiate con il nome in basso a sinistra; anello d'accento sotto la macchina scelta.
- **Movimento:** guasti che pulsano lentamente, rotazione lenta dell'anteprima, oscillazione leggera della M. Niente di tutto questo con "riduci movimento".
- **Sempre una versione 2D o SVG** pronta subito; il 3D arriva dopo, senza spostare la pagina.

## Manutenzione preventiva

- **Stato del piano** sempre con icona e testo: "In regola" (verde), "In scadenza" (ambra, orologio), "Scaduto" (rosso, triangolo), "Sospeso" (grigio, pausa).
- **Rispetto dei piani:** percentuale in un anello colorato (verde da 90%, ambra da 70%, rosso sotto), con i quattro numeri accanto; mai solo il colore.
- **Tipo di piano** scelto con due schede grandi (A tempo / A contatore) con icona calendario o contachilometri; le opzioni cambiano subito.
- **Calendario:** griglia del mese su PC, settimana da lunedì, oggi cerchiato nell'accento. Ordini generati pieni (barrati quando conclusi, rossi se scaduti); scadenze previste tratteggiate; stimate con l'icona del contatore. Sul telefono diventa un elenco per giorno, con schede da toccare.

## Magazzino

- **Stato della scorta** sempre con icona e testo: "Disponibile" (verde, spunta), "Sotto scorta" (ambra, triangolo), "Esaurito" (rosso, croce). Le stesse pastiglie fanno da filtro nell'elenco, con i conteggi.
- **Quantità** in cifre tabellari (`.num`), seguite dall'unità in grigio; i decimali compaiono solo se servono (`2,5 l`, `12 pz`).
- **Scheda ricambio:** in alto giacenza in grande, scorta minima, costo medio e valore (questi ultimi solo a chi vede i costi); sotto, i movimenti come pulsanti grandi, "Preleva" pieno. Il riquadro del movimento si apre sotto i pulsanti, come i passaggi dell'ordine.
- **Movimenti:** entrate in verde con `+`, uscite con `−` e icona ambra; sotto ogni movimento la giacenza risultante.
- **Ricambi nell'ordine:** sezione con "Preleva ricambio"; la distinta della macchina compare per prima, come elenco di schede da toccare con la giacenza a destra (rossa se esaurito).
- **Da riordinare:** una scheda per fornitore, con "Copia l'elenco" e stampa; in stampa restano solo le tabelle.

## App installabile

- **Procedura guidata** (`/app`):
  - indicatore a tre passi;
  - illustrazioni animate costruite in HTML e CSS (`<x-pwa-steps>`: schermo, barra di Safari o Chrome, tasto evidenziato che pulsa, foglio che sale, icona che compare sulla Home);
  - con movimento ridotto le illustrazioni restano ferme.
- **Avviso "Nuova versione disponibile"** (`<x-update-banner>`): riquadro in basso, sopra la barra di navigazione del telefono, con "Aggiorna" e la chiusura (×, "Più tardi").
- **Pagina offline:** autonoma, senza CSS esterno, scura, icona senza rete e pulsante "Riprova".

## Accessibilità

- Contrasto **WCAG AA** per testo e stati, in entrambi i temi.
- Focus sempre visibile (`:focus-visible`, contorno dell'accento).
- Navigazione completa da tastiera:
  - collegamento "Vai al contenuto";
  - palette comandi con le frecce;
  - `Esc` chiude le finestre.
- Etichette per gli screen reader su icone senza testo, logo e finestre di dialogo (`role="dialog"`, `aria-modal`).
- Bersagli di tocco di almeno 44 × 44 px.

## Movimento

- Ingresso pagina: `mt-rise`, 350 ms.
- Pulsanti: compressione al tocco (`scale(.97)`).
- Schede: inclinazione 3D di massimo 8–10°.
- `prefers-reduced-motion: reduce` porta a zero animazioni e transizioni.

## Icone

Set **Lucide** (ISC), griglia 24 px, tratto 2, angoli arrotondati. Per aggiungerne una basta copiare il contenuto SVG da lucide.dev in `resources/icons/lucide.php`.
