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
        'push_urgent' => 'Push urgenti',
        'photos' => 'Foto',
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
        'install_lock' => 'Installazione dal browser bloccata',
        'install_lock_open' => 'Manca il file storage/app/installed.lock oppure MANUTRACK_INSTALL_TOKEN nel .env non è vuoto: svuotalo.',
        'vapid_subject' => 'Contatto delle chiavi push (VAPID_SUBJECT)',
        'vapid_subject_fake' => '":subject" non è un contatto reale: scrivi nel .env VAPID_SUBJECT="mailto:" seguito da un tuo indirizzo email.',
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

    'mail_test' => [
        'title' => 'Email di prova',
        'intro' => 'Invia subito un\'email per controllare la posta in uscita (invio: :mailer, mittente: :from). Se arriva nello spam, controlla SPF, DKIM e DMARC del dominio del mittente (docs/INSTALLAZIONE.md).',
        'button' => 'Invia un\'email di prova a :email',
        'subject' => 'ManuTrack: email di prova',
        'body' => "Questa è un'email di prova inviata da ManuTrack (:app) il :time.\n\nSe la ricevi, la posta in uscita funziona. Se è finita nello spam, controlla SPF, DKIM e DMARC del dominio del mittente.",
        'sent' => 'Email di prova inviata a :email (mittente :from). Controlla la casella, anche lo spam.',
        'failed' => 'Il server di posta ha rifiutato l\'invio: :message',
        'not_real' => 'L\'invio è impostato su ":mailer": le email non partono davvero. Imposta MAIL_MAILER=smtp e i dati del server di posta nel file .env.',
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

    // Come partono le notifiche urgenti su questo server
    'push_urgent' => [
        'fpm' => 'subito dopo la risposta (PHP-FPM)',
        'litespeed' => 'subito dopo la risposta (LiteSpeed)',
        'none' => 'subito, ma la pagina può attendere qualche secondo l\'invio',
    ],
    'photos' => [
        'webp' => 'WebP, 1600 px, senza GPS',
        'jpeg' => 'JPEG ridotto: GD/Imagick senza supporto WebP',
        'none' => 'originali, non ridotte: mancano GD e Imagick',
    ],
    'example_accounts' => [
        'title' => '{1} C\'è un account con un indirizzo d\'esempio|[2,*] Ci sono :count account con indirizzi d\'esempio',
        'text' => 'Indirizzi @example.com (o .org, .net) sono riservati agli esempi: in produzione sono quasi certamente account di prova, magari con una password nota. Disattivali o assegna loro un indirizzo vero.',
        'open' => 'Apri Persone',
    ],
];
