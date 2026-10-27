<?php

return [
    'title' => 'Avvisi',
    'unread' => '{0} nessun avviso da leggere|{1} :count avviso da leggere|[2,*] :count avvisi da leggere',
    'unread_only' => 'Solo da leggere',
    'all_read' => 'Segna tutti come letti',
    'all_read_done' => 'Tutti gli avvisi sono letti.',
    'today' => 'Oggi',
    'yesterday' => 'Ieri',
    'new' => 'Nuovo',
    'none' => 'Nessun avviso. Qui trovi tutto ciò che ti è stato notificato, anche se il telefono era spento.',
    'none_unread' => 'Hai letto tutto.',

    'channels' => [
        'push' => 'Notifica sul telefono',
        'email' => 'Email',
    ],
    'email_modes' => [
        'no' => 'Nessuna email',
        'riepilogo' => 'Riepilogo giornaliero',
        'subito' => 'Email subito',
    ],

    'events' => [
        'segnalazione_nuova' => ['label' => 'Nuova segnalazione', 'description' => 'Qualcuno segnala un guasto da valutare.'],
        'segnalazione_urgente' => ['label' => 'Segnalazione urgente', 'description' => 'Segnalazione critica o macchina importante ferma (arriva subito).'],
        'segnalazione_esito' => ['label' => 'Esito della mia segnalazione', 'description' => 'La tua segnalazione è stata presa in carico o respinta.'],
        'macchina_ferma' => ['label' => 'Macchina ferma o ripartita', 'description' => 'Una macchina va in guasto o torna operativa.'],
        'ordine_assegnato' => ['label' => 'Ordine assegnato a me', 'description' => 'Ti è stato assegnato un ordine di lavoro.'],
        'ordine_completato' => ['label' => 'Intervento completato', 'description' => 'L\'intervento sulla tua segnalazione è finito.'],
        'preventiva_da_assegnare' => ['label' => 'Preventiva da assegnare', 'description' => 'Un piano preventivo ha creato un ordine senza assegnatario.'],
        'scorta_minima' => ['label' => 'Ricambio sotto scorta', 'description' => 'Un ricambio scende alla scorta minima.'],
        'aggiornamento' => ['label' => 'Aggiornamento di ManuTrack', 'description' => 'Un aggiornamento è stato applicato o non è riuscito.'],
        'rapporto_mensile' => ['label' => 'Rapporto mensile', 'description' => 'Il rapporto del mese appena chiuso è pronto.'],
    ],

    'preferences' => [
        'title' => 'Preferenze degli avvisi',
        'intro' => 'Scegli come vuoi essere avvisato per ogni cosa. Le notifiche sul telefono arrivano ai dispositivi attivati; le email all\'indirizzo del tuo profilo.',
        'no_device' => 'Nessun dispositivo attivato per le notifiche: <a href=":url" class="underline">attiva il telefono</a>.',
        'in_app' => 'Ogni avviso resta comunque nel centro avvisi dell\'app per 90 giorni.',
        'saved' => 'Preferenze salvate.',
    ],

    'rules' => [
        'title' => 'Avvisi via email per ruolo',
        'intro' => 'Per ogni ruolo scegli se l\'email parte subito, finisce nel riepilogo giornaliero delle 7:10 o non parte. È la scelta predefinita: ogni persona può cambiarla nelle proprie preferenze.',
        'event' => 'Evento',
        'note' => 'Le notifiche sul telefono si scelgono nelle preferenze personali. L\'avviso nel centro avvisi c\'è sempre.',
        'saved' => 'Regole salvate.',
        'audit' => 'Avvisi via email per ruolo',
    ],

    'mail' => [
        'open' => 'Apri in ManuTrack',
        'why' => 'Ricevi questa email per l\'evento ":event".',
        'change' => 'Puoi cambiare gli avvisi da: :url',
        'salutation' => 'ManuTrack',
    ],

    'digest' => [
        'subject' => '{1} :count avviso di oggi · :company|[2,*] :count avvisi di oggi · :company',
        'greeting' => '{1} Il tuo riepilogo: :count avviso|[2,*] Il tuo riepilogo: :count avvisi',
        'more' => '{1} …e :count altro avviso nell\'app.|[2,*] …e altri :count avvisi nell\'app.',
        'open' => 'Apri il centro avvisi',
        'done' => 'Riepiloghi inviati: :sent. Avvisi vecchi eliminati: :pruned.',
    ],

    'update' => [
        'ok' => 'ManuTrack aggiornato alla :version',
        'ok_body' => 'L\'aggiornamento è stato applicato senza errori.',
        'failed' => 'Aggiornamento alla :version non riuscito',
    ],
];
