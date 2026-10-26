<?php

return [
    'title' => 'Installazione',
    'heading' => 'Installa ManuTrack',
    'intro' => 'Questa procedura prepara il database, crea l\'amministratore e salva le impostazioni dell\'azienda. Si usa una sola volta: al termine viene disattivata.',

    'requirements' => 'Requisiti del server',
    'requirements_failed' => 'Il server non soddisfa i requisiti indispensabili: correggi le voci in rosso e ricarica la pagina.',
    'already_installed' => 'ManuTrack è già installato: l\'installazione non può ripartire.',
    'busy' => 'Un\'altra installazione è in corso. Attendi qualche secondo e riprova.',
    'failed' => 'Installazione non riuscita: :message',
    'token_missing' => 'Per sicurezza l\'installazione dal browser richiede un codice. Scrivi un valore lungo e casuale in MANUTRACK_INSTALL_TOKEN nel file .env, poi ricarica la pagina.',
    'token_invalid' => 'Il codice di installazione non corrisponde a MANUTRACK_INSTALL_TOKEN nel file .env.',
    'env_not_writable' => 'Il file .env non è scrivibile.',
    'password_hint' => 'Almeno 10 caratteri, con lettere e numeri.',

    'section' => [
        'security' => 'Sicurezza',
        'company' => 'Azienda',
        'admin' => 'Amministratore',
        'options' => 'Opzioni',
    ],

    'field' => [
        'token' => 'Codice di installazione',
        'token_hint' => 'Il valore di MANUTRACK_INSTALL_TOKEN nel file .env.',
        'company' => 'Nome dell\'azienda',
        'name' => 'Nome e cognome',
        'email' => 'Email',
        'password' => 'Password',
        'password_confirmation' => 'Ripeti la password',
        'palette' => 'Colori dell\'interfaccia',
        'vapid' => 'Genera le chiavi per le notifiche push',
        'vapid_hint' => 'Consigliato. Si possono generare anche dopo con "php artisan manutrack:vapid".',
    ],

    'submit' => 'Installa',

    'step' => [
        'key' => 'Chiave dell\'applicazione',
        'migrate' => 'Tabelle del database',
        'settings' => 'Impostazioni dell\'azienda',
        'admin' => 'Amministratore',
        'vapid' => 'Chiavi per le notifiche push',
        'done' => 'Installazione registrata',
    ],

    'done_title' => 'Installazione completata',
    'done_text' => 'ManuTrack :version è pronto. L\'installazione dal browser è stata disattivata.',
    'done_cli' => 'ManuTrack :version installato. Amministratore: :email',
    'done_token_removed' => 'Il codice di installazione è stato cancellato dal file .env.',
    'done_token_manual' => 'Il file .env non è scrivibile: cancella a mano il valore di MANUTRACK_INSTALL_TOKEN.',
    'done_go' => 'Vai all\'app',
    'next' => [
        'title' => 'Ultimi passi',
        'cron' => 'Aggiungi il cron di Laravel (una voce, ogni minuto): vedi docs/INSTALLAZIONE.md.',
        'https' => 'Verifica che il sito risponda solo in HTTPS: è indispensabile per l\'app installabile e le notifiche.',
    ],

    'vapid_exists' => 'Le chiavi push esistono già. Usa --force per sostituirle.',
    'vapid_written' => 'Chiavi push generate e salvate nel file .env.',
    'vapid_manual' => 'Il file .env non è scrivibile: copia queste righe nel file .env.',
    'vapid_missing' => 'Chiavi push non configurate.',
    'vapid_rotated' => 'Le chiavi sono cambiate: chi aveva attivato le notifiche dovrà riattivarle.',
    'icons_need_gd' => 'Per generare le icone serve l\'estensione GD (o Imagick). Senza, l\'app usa le icone già incluse nel pacchetto.',
    'palette_unknown' => 'Palette sconosciuta: :name',

    'req' => [
        'php' => 'PHP :version o successivo',
        'extension' => 'Estensione :name',
        'present' => 'presente',
        'missing' => 'mancante',
        'recommended_for' => 'consigliata per :why',
        'image' => 'Elaborazione immagini (GD o Imagick)',
        'image_fallback' => 'assente: si useranno le icone pre-generate e le foto non verranno ridimensionate',
        'writable' => 'Cartella :path scrivibile',
        'writable_file' => 'File :path scrivibile',
        'yes' => 'sì',
        'not_writable' => 'non scrivibile: imposta i permessi a 775',
        'env_manual' => 'non scrivibile: chiavi e codici andranno copiati a mano',
        'database' => 'Connessione al database',
        'db_error' => 'non riuscita: :message',
    ],
];
