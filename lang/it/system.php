<?php

return [
    'title' => 'Sistema',
    'intro' => 'Versione, aggiornamenti, copie del database e stato del server di questa installazione.',

    'actions' => [
        'apply_now' => 'Applica ora',
        'retry_cron' => 'Fai ritentare al cron',
        'backup_now' => 'Crea una copia ora',
    ],

    'version' => [
        'title' => 'Versione',
        'code' => 'Codice caricato',
        'installed' => 'Applicata al database',
        'none' => 'mai',
    ],

    'cards' => [
        'cron' => 'Cron',
        'queue' => 'Coda',
        'push' => 'Notifiche push',
        'php' => 'PHP',
        'database' => 'Database',
        'images' => 'Immagini',
    ],

    'history' => [
        'title' => 'Aggiornamenti',
        'empty' => 'Nessun aggiornamento registrato.',
        'version' => 'Versione',
        'from' => 'Da',
        'trigger' => 'Avvio',
        'status' => 'Esito',
        'date' => 'Data',
        'duration' => 'Durata',
        'backup' => 'Copia',
        'triggers' => ['install' => 'installazione', 'manual' => 'comando', 'auto' => 'cron', 'web' => 'pannello'],
        'statuses' => ['running' => 'in corso', 'success' => 'riuscito', 'failed' => 'fallito'],
    ],

    'backups' => [
        'title' => 'Copie del database',
        'intro' => 'Create in automatico prima di ogni aggiornamento. Si conservano le ultime :keep.',
        'empty' => 'Nessuna copia presente.',
        'restore_hint' => 'Per ripristinare: php artisan manutrack:restore',
    ],

    'checks' => [
        'title' => 'Requisiti',
        'ok' => 'OK',
        'warning' => 'Attenzione',
        'error' => 'Errore',
    ],

    'banner' => [
        'failed_title' => 'L\'aggiornamento alla versione :version non è riuscito',
        'failed_text' => 'L\'app funziona con i dati di prima. Trovi l\'errore e la copia del database in Sistema.',
        'pending_title' => 'Aggiornamento alla versione :version in attesa',
        'pending_text' => 'Il cron lo applicherà a breve, appena il caricamento dei file sarà completo.',
        'details' => 'Dettagli',
    ],

    'update' => [
        'auto_disabled' => 'Aggiornamento automatico disattivato (MANUTRACK_AUTO_UPDATE=false).',
        'not_installed' => 'ManuTrack non è ancora installato.',
        'up_to_date' => 'Già aggiornato alla versione :version.',
        'no_marker' => 'Manca release.json: il caricamento non è completo oppure è una copia di sviluppo.',
        'marker_mismatch' => 'release.json è della versione :marker ma il codice è :code: il caricamento non è completo.',
        'incomplete' => 'Caricamento incompleto (:count problemi, ad esempio: :first).',
        'settling' => 'File modificati di recente: attendo ancora circa :minutes min prima di aggiornare.',
        'failed_before' => 'L\'aggiornamento a :version era già fallito. Per ritentare: php artisan manutrack:update --retry (o dal pannello Sistema).',
        'locked' => 'Un altro aggiornamento o ripristino è in corso.',
        'retry_armed' => 'Il cron ritenterà l\'aggiornamento alla versione :version al prossimo giro.',
        'step_backup' => 'Copia del database',
        'step_down' => 'Modalità manutenzione',
        'step_migrate' => 'Migrazioni',
        'step_sync' => 'Allineamento dei dati di sistema',
        'step_cache' => 'Cache',
        'done' => 'Aggiornamento completato: :from → :to',
        'backup_saved' => 'Copia del database: :file',
        'failed' => 'Aggiornamento a :version non riuscito: :message',
        'restore_hint' => 'Per tornare ai dati di prima: php artisan manutrack:restore :file',
    ],

    'backup' => [
        'created' => 'Copia creata: :file (:size)',
        'pruned' => 'Eliminate :count copie vecchie (se ne conservano :keep).',
        'failed' => 'Copia non riuscita: :message',
        'file' => 'File',
        'size' => 'Dimensione',
        'date' => 'Data',
    ],

    'restore' => [
        'none' => 'Non ci sono copie in storage/app/backups.',
        'need_file' => 'Indica il nome del file da ripristinare.',
        'choose' => 'Quale copia vuoi ripristinare?',
        'not_found' => 'Copia non trovata: :file',
        'confirm' => 'Il database attuale verrà sostituito con :file. Continuare?',
        'cancelled' => 'Ripristino annullato.',
        'safety' => 'Copia dello stato attuale: :file',
        'running' => 'Ripristino',
        'done' => 'Database ripristinato da :file.',
        'failed' => 'Ripristino non riuscito: :message',
        'interrupted' => 'Interrotto dal ripristino della copia :file.',
    ],
];
