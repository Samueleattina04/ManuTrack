<?php

return [
    'banner' => 'Versione dimostrativa: i dati sono inventati e vengono azzerati ogni notte alle :time.',
    'login_hint' => 'Prova con :email e la password :password.',

    'blocked' => [
        'package' => 'Questo non è il pacchetto demo: l\'installazione dimostrativa si crea solo con manutrack-demo-<versione>.zip.',
        'env' => 'Nel file .env manca MANUTRACK_DEMO=true.',
        'database' => 'Questo database appartiene a un\'installazione ":edition", non demo: non viene toccato.',
        'unreadable' => 'Il database non si riesce a leggere (connessione o permessi): per sicurezza non viene toccato.',
        'seeders' => 'Nel pacchetto mancano i dati dimostrativi (database/seeders).',
        'install' => 'Questo è il pacchetto demo: non si usa per installare un cliente. Per la demo usa "php artisan manutrack:demo"; per un cliente carica il pacchetto manutrack-<versione>.zip.',
        'update' => 'Il pacchetto caricato è dell\'edizione ":package" ma questa installazione è ":database": aggiornamento non applicato. Carica il pacchetto giusto.',
    ],

    'mismatch' => [
        'package_db' => 'Il codice è dell\'edizione ":package" ma il database è ":database". Carica il pacchetto giusto prima di qualsiasi altra operazione.',
        'env_on' => 'MANUTRACK_DEMO=true in un\'installazione che non è demo: toglilo dal file .env.',
        'env_off' => 'Installazione demo senza MANUTRACK_DEMO=true: l\'azzeramento notturno è fermo.',
    ],

    'step' => [
        'database' => 'Database ricreato da zero',
        'files' => 'File caricati e sessioni cancellati',
        'data' => 'Dati dimostrativi',
        'failed' => 'Il comando :command non è riuscito: :output',
    ],

    'command' => [
        'exists' => 'L\'installazione demo esiste già. Per azzerarla usa --azzera.',
        'done' => 'Demo pronta. Accesso: :email / :password',
    ],

    'system' => [
        'title' => 'Edizione',
        'cliente' => 'Cliente',
        'demo' => 'Demo (azzeramento ogni notte alle :time, ultimo: :last)',
        'sviluppo' => 'Sviluppo',
        'never' => 'mai',
    ],
];
