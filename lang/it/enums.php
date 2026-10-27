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
];
