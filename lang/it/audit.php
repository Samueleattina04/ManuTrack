<?php

return [
    'title' => 'Registro attività',
    'search_placeholder' => 'Persona, dettaglio o indirizzo IP',
    'area' => 'Area',
    'all' => 'Tutte',
    'when' => 'Quando',
    'who' => 'Chi',
    'what' => 'Cosa',
    'detail' => 'Dettaglio',
    'from' => 'Da',
    'changes' => 'Modifiche',
    'empty' => 'Nessuna attività registrata.',

    'groups' => [
        'auth' => 'Accessi',
        'users' => 'Persone',
        'roles' => 'Ruoli',
        'settings' => 'Impostazioni',
        'system' => 'Sistema',
        'profile' => 'Profilo',
    ],

    'actions' => [
        'auth' => [
            'login' => 'Accesso',
            'logout' => 'Uscita',
            'failed' => 'Accesso non riuscito',
            'password_reset' => 'Password reimpostata',
            '2fa_enabled' => 'Verifica in 2 passaggi attivata',
            '2fa_disabled' => 'Verifica in 2 passaggi disattivata',
        ],
        'users' => [
            'invited' => 'Invito inviato',
            'invitation_accepted' => 'Invito accettato',
            'invitation_revoked' => 'Invito annullato',
            'role_changed' => 'Ruolo cambiato',
            'activated' => 'Persona riattivata',
            'deactivated' => 'Persona disattivata',
            '2fa_reset' => 'Verifica in 2 passaggi azzerata',
            'password_link_sent' => 'Link per nuova password inviato',
        ],
        'roles' => [
            'permission_granted' => 'Permesso aggiunto',
            'permission_revoked' => 'Permesso tolto',
            'defaults_restored' => 'Permessi predefiniti ripristinati',
        ],
        'settings' => [
            'company_updated' => 'Impostazioni dell\'azienda modificate',
            'logo_uploaded' => 'Logo caricato',
            'logo_removed' => 'Logo rimosso',
        ],
        'profile' => [
            'updated' => 'Profilo modificato',
            'password_changed' => 'Password cambiata',
        ],
        'system' => [
            'update_success' => 'Aggiornamento applicato',
            'update_failed' => 'Aggiornamento non riuscito',
            'update_up_to_date' => 'Aggiornamento: già aggiornato',
            'update_locked' => 'Aggiornamento: già in corso',
            'update_retry_armed' => 'Nuovo tentativo di aggiornamento richiesto',
            'backup_created' => 'Copia del database creata',
        ],
    ],
];
