<?php

return [
    'asset_status' => [
        'operativa' => 'Operativa',
        'guasto' => 'In guasto',
        'manutenzione' => 'In manutenzione',
        'ferma' => 'Ferma',
        'dismessa' => 'Dismessa',
    ],
    'priority' => [
        'bassa' => 'Bassa',
        'media' => 'Media',
        'alta' => 'Alta',
        'critica' => 'Critica',
    ],
    'location_type' => [
        'sito' => 'Sito',
        'area' => 'Area',
        'linea' => 'Linea',
    ],
    'criticality' => [
        'A' => 'A · ferma la linea',
        'B' => 'B · rallenta',
        'C' => 'C · non blocca',
    ],
    'work_order_status' => [
        'aperto' => 'Aperto',
        'pianificato' => 'Pianificato',
        'in_corso' => 'In corso',
        'in_attesa' => 'In attesa',
        'completato' => 'Completato',
        'chiuso' => 'Chiuso',
        'annullato' => 'Annullato',
    ],
    'work_order_type' => [
        'correttivo' => 'Correttivo',
        'preventivo' => 'Preventivo',
        'miglioramento' => 'Miglioramento',
    ],
    'waiting_reason' => [
        'ricambi' => 'Ricambi',
        'fornitore' => 'Fornitore esterno',
        'produzione' => 'Produzione (macchina non disponibile)',
    ],
    'request_status' => [
        'nuova' => 'Da valutare',
        'accettata' => 'Accettata',
        'respinta' => 'Respinta',
        'duplicata' => 'Duplicata',
    ],
    'failure_kind' => [
        'problema' => 'Problema',
        'causa' => 'Causa',
        'rimedio' => 'Rimedio',
    ],
    'movement_type' => [
        'carico' => 'Carico',
        'prelievo' => 'Prelievo',
        'reso' => 'Reso',
        'rettifica' => 'Rettifica inventario',
        'trasferimento' => 'Trasferimento',
    ],
];
