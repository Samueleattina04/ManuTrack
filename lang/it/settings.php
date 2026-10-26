<?php

return [
    'save' => 'Salva',
    'saved' => 'Impostazioni salvate.',

    'company' => [
        'title' => 'Impostazioni dell\'azienda',
        'section' => 'Azienda',
        'name' => 'Nome dell\'azienda',
        'timezone' => 'Fuso orario',
        'locale' => 'Lingua dell\'interfaccia',
    ],
    'locales' => [
        'it' => 'Italiano',
        'en' => 'English',
    ],

    'logo' => [
        'title' => 'Logo dell\'azienda',
        'intro' => 'Compare in alto nella barra laterale, sul telefono e nella pagina di accesso. ManuTrack resta in piccolo nel piè di pagina.',
        'current' => 'Logo attuale',
        'current_dark' => 'Logo per il tema scuro',
        'light' => 'Versione principale (fondi chiari)',
        'dark' => 'Versione per il tema scuro (facoltativa)',
        'dark_hint' => 'Ad esempio con la scritta chiara. Se manca, nel tema scuro il logo principale compare su una targhetta chiara.',
        'none' => 'Nessun logo caricato',
        'upload' => 'Carica il logo',
        'replace' => 'Sostituisci',
        'remove' => 'Rimuovi',
        'remove_confirm' => 'Rimuovere il logo? Al suo posto comparirà il nome dell\'azienda.',
        'uploading' => 'Caricamento…',
        'uploaded' => 'Logo caricato.',
        'removed' => 'Logo rimosso.',
        'rules' => 'PNG, JPG o WebP fino a 2 MB, meglio con sfondo trasparente e in orizzontale. Viene ridotto a 720×240 px al massimo.',
        'no_driver' => 'Sul server mancano GD e Imagick: il file viene salvato così com\'è.',
        'invalid' => 'Il file non è un\'immagine PNG, JPG o WebP valida.',
        'unreadable' => 'L\'immagine non si riesce a leggere: prova a salvarla di nuovo in PNG.',
    ],

    'colors' => [
        'title' => 'Colori',
        'intro' => 'La M di ManuTrack, i pulsanti e le icone dell\'app seguono la palette scelta. Gli stati delle macchine (operativa, guasto, manutenzione, ferma) hanno colori propri e restano uguali per tutti.',
        'custom' => 'Personalizzata',
        'accent' => 'Colore principale',
        'accent_hint' => 'Per i pulsanti: viene scurito se serve, così il testo bianco resta leggibile.',
        'soft' => 'Colore chiaro',
        'soft_hint' => 'Per evidenziazioni e dettagli (facoltativo).',
        'sample' => 'Prendi i colori dal logo',
        'sampled' => 'Colori presi dal logo: controlla l\'anteprima e salva.',
        'sample_failed' => 'Nel logo non ci sono colori abbastanza decisi da usare: scegli i colori a mano.',
        'sample_button' => 'Nuovo ordine',
        'sample_text' => 'Testo d\'accento',
        'contrast_button' => 'pulsante :value:1',
        'contrast_text' => 'testo :value:1',
        'aa_note' => 'Contrasti WCAG: almeno 4,5:1 per testi e pulsanti. Il calcolo tiene conto dei due temi.',
    ],
];
