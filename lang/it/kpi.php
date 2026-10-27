<?php

return [
    'title' => 'Indicatori',
    'period' => 'Periodo',
    'from' => 'Dal',
    'to' => 'Al',
    'all' => 'Tutte',
    'how' => 'Come si calcolano',
    'hours' => 'Ore',
    'period_hint' => 'Dal :from al :to (:tz). Il periodo comprende i giorni "dal" e "al"; se finisce nel futuro si ferma ad adesso.',
    'insufficient' => 'Dati insufficienti',
    'insufficient_short' => 'insufficienti',
    'retired' => 'dismessa',
    'by_asset' => 'Per macchina',
    'no_events' => 'Nessun fermo né ordine concluso nel periodo.',

    'presets' => [
        '30g' => 'Ultimi 30 giorni',
        'mese' => 'Mese corrente',
        'mese_prec' => 'Mese precedente',
        '3m' => 'Ultimi 3 mesi',
        'anno' => 'Anno corrente',
        '12m' => 'Ultimi 12 mesi',
        'scelta' => 'Date a scelta',
    ],

    'mttr' => [
        'title' => 'MTTR · tempo medio di ripristino',
        'short' => 'MTTR',
        'on' => '{1} su :n fermo per guasto concluso|[2,*] su :n fermi per guasto conclusi',
        'need' => '{0} Nessun fermo per guasto concluso; ne servono almeno :min.|{1} Solo :n fermo per guasto concluso; ne servono almeno :min.|[2,*] Solo :n fermi per guasto conclusi; ne servono almeno :min.',
    ],

    'mtbf' => [
        'title' => 'MTBF · tempo medio tra i guasti',
        'short' => 'MTBF',
        'on' => ':n guasti su :assets macchine osservate · circa :days giorni',
        'need' => ':n guasti in :days giorni-macchina di osservazione: servono almeno :min guasti e 7 giorni-macchina.',
        'no_failures' => 'Nessun guasto',
    ],

    'downtime' => [
        'title' => 'Fermi',
        'split' => 'per guasto :failures (:fh h) · pianificati :planned (:ph h)',
        'open' => '{1} :n ancora aperto|[2,*] :n ancora aperti',
    ],

    'backlog' => [
        'title' => 'Arretrato',
        'overdue' => '{1} :n scaduto|[2,*] :n scaduti',
        'age' => 'età media :days giorni',
        'none' => 'nessun ordine aperto',
        'now' => 'situazione di adesso',
    ],

    'mix' => [
        'title' => 'Preventivo e correttivo',
        'preventive' => 'Preventivo',
        'corrective' => 'Correttivo',
        'improvement' => 'Miglioramenti (a parte)',
        'need' => 'Ordini preventivi e correttivi completati: :n; ne servono almeno :min.',
        'aria' => 'Preventivo :p, correttivo :c',
    ],

    'compliance' => [
        'need' => 'Interventi dovuti nel periodo: :n; ne servono almeno :min.',
    ],

    'costs' => [
        'title' => 'Costi',
        'labor' => 'Manodopera',
        'parts' => 'Ricambi',
        'external' => 'Esterni',
        'total' => 'Totale',
        'no_asset' => 'Senza macchina',
        'orders' => '{1} :n ordine|[2,*] :n ordini',
        'none' => 'Nessun ordine concluso nel periodo.',
    ],

    'notes' => [
        'Fermi: quelli che si sovrappongono al periodo; conta solo la parte dentro il periodo. Un fermo è "pianificato" se è legato a un ordine preventivo o di miglioramento, altrimenti è un guasto.',
        'MTTR: durata media dei fermi per guasto conclusi nel periodo (per intero), dal fermo alla ripartenza. I fermi aperti non contano.',
        'MTBF: tempo di funzionamento (calendario, 24 ore su 24, meno i fermi) diviso il numero di guasti iniziati nel periodo. Escluse le macchine dismesse e quelle non ancora installate.',
        'Arretrato: ordini aperti adesso, indipendentemente dal periodo.',
        'Preventivo / correttivo e costi: ordini completati nel periodo; annullati esclusi; i miglioramenti sono a parte nel rapporto.',
        'Rispetto dei piani: interventi con scadenza prevista nel periodo, fino a oggi.',
        'Con pochi dati il valore non viene mostrato ("dati insufficienti"). Definizioni complete in docs/KPI.md.',
    ],

    'errors' => [
        'date' => 'Data non valida.',
        'order' => 'La data "al" viene prima della data "dal".',
        'future' => 'Il periodo comincia nel futuro.',
    ],
];
