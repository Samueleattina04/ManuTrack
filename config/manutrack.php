<?php

/*
|--------------------------------------------------------------------------
| ManuTrack
|--------------------------------------------------------------------------
|
| Configurazione specifica di un'installazione. Ogni cliente ha la propria
| copia dell'app e il proprio .env: qui si leggono i valori iniziali, che le
| impostazioni salvate nel database (tabella "settings") possono sostituire.
|
*/

return [

    // Valori iniziali dell'azienda (modificabili poi da Impostazioni › Azienda)
    'company' => [
        'name' => env('MANUTRACK_COMPANY_NAME', 'La mia azienda'),
        'palette' => env('MANUTRACK_PALETTE', 'rame'),
        'accent' => env('MANUTRACK_ACCENT'),
    ],

    // Preimpostazioni di colore. "d" = tema scuro, "l" = tema chiaro.
    // accent = pulsanti e superfici piene (testo sopra: on_accent);
    // accent_fg = testi e icone d'accento sul fondo (facoltativo, predefinito = accent);
    // soft = aloni ed evidenziazioni (facoltativo, predefinito = accent);
    // logo_light/logo_dark sono le due facce della M.
    'palettes' => [
        'rame' => [
            'label' => 'Grafite e Rame',
            'accent_d' => '#E8915A', 'accent2_d' => '#F6B88C', 'on_accent_d' => '#1B0E06',
            'accent_l' => '#A64B17', 'accent2_l' => '#C4622A', 'on_accent_l' => '#FFFFFF',
            'logo_light_d' => '#EE9455', 'logo_dark_d' => '#5E2910',
            'logo_light_l' => '#DD7536', 'logo_dark_l' => '#33190D',
        ],
        'ciano' => [
            'label' => 'Acciaio e Ciano',
            'accent_d' => '#3FCBE4', 'accent2_d' => '#93E5F3', 'on_accent_d' => '#03161B',
            'accent_l' => '#0A6C80', 'accent2_l' => '#0E879F', 'on_accent_l' => '#FFFFFF',
            'logo_light_d' => '#3CC6E0', 'logo_dark_d' => '#0C3B47',
            'logo_light_l' => '#169FB9', 'logo_dark_l' => '#0A2830',
        ],
        'indaco' => [
            'label' => 'Ardesia e Indaco',
            'accent_d' => '#9C96FF', 'accent2_d' => '#C6C2FF', 'on_accent_d' => '#0E0B2A',
            'accent_l' => '#4B3FCF', 'accent2_l' => '#6357E3', 'on_accent_l' => '#FFFFFF',
            'logo_light_d' => '#9089FF', 'logo_dark_d' => '#28226B',
            'logo_light_l' => '#6A5EEE', 'logo_dark_l' => '#1C184A',
        ],
        // Valori provvisori: da ricampionare dal logo del cliente (pistacchio chiaro e verde foglia)
        'pistacchio' => [
            'label' => 'Pistacchio',
            'accent_d' => '#3A7D3F', 'accent2_d' => '#3E8343', 'on_accent_d' => '#FFFFFF',
            'accent_l' => '#2A5F2F', 'accent2_l' => '#336E38', 'on_accent_l' => '#FFFFFF',
            'accent_fg_d' => '#B5D67F', 'accent_fg_l' => '#2A5F2F',
            'soft_d' => '#A8CB6E', 'soft_l' => '#8DB451',
            'logo_light_d' => '#A8CB6E', 'logo_dark_d' => '#24502A',
            'logo_light_l' => '#8DB451', 'logo_dark_l' => '#1E4224',
        ],
    ],

    // Installazione dal browser: senza questo codice la procedura non parte.
    'install_token' => env('MANUTRACK_INSTALL_TOKEN'),

    // Aggiornamenti
    'update' => [
        // Il cron applica da solo un pacchetto nuovo, se completo
        'auto' => (bool) env('MANUTRACK_AUTO_UPDATE', true),
        // Minuti di quiete dopo l'ultima modifica dei file prima di aggiornare
        'settle_minutes' => (int) env('MANUTRACK_UPDATE_SETTLE_MINUTES', 5),
        // Mette in cache anche la configurazione (le modifiche a .env richiedono poi un aggiornamento)
        'cache_config' => (bool) env('MANUTRACK_CACHE_CONFIG', false),
        // Cartella in cui cercare release.json (predefinito: la cartella dell'app)
        'base_path' => null,
    ],

    // Copie del database
    'backups' => [
        'path' => storage_path('app/backups'),
        'keep' => (int) env('MANUTRACK_BACKUPS_KEEP', 5),
        'chunk' => 500,
    ],

    // Installazione dimostrativa con azzeramento notturno
    'demo' => (bool) env('MANUTRACK_DEMO', false),

    // Foto e documenti allegati (disco privato, mai raggiungibili direttamente dal web)
    'attachments' => [
        'disk' => env('MANUTRACK_ATTACHMENTS_DISK', 'local'),
        'max_mb' => (int) env('MANUTRACK_UPLOAD_MAX_MB', 20),
        // Lato lungo delle foto salvate e delle miniature
        'photo_max' => 1600,
        'thumb_max' => 400,
        'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'webp', 'dwg', 'dxf'],
    ],

    // Notifiche push (chiavi generate con "php artisan manutrack:vapid")
    'webpush' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

];
