<?php

/*
 * Solo le voci diverse dai valori predefiniti di Livewire.
 * I file caricati restano temporanei (cartella livewire-tmp del disco privato)
 * finché non vengono salvati come allegati.
 */
return [
    'temporary_file_upload' => [
        'disk' => null,
        // Il limite vero lo decide l'azione che salva (foto e documenti hanno limiti diversi)
        'rules' => ['required', 'file', 'max:'.(1024 * (int) env('MANUTRACK_UPLOAD_MAX_MB', 20))],
        'directory' => null,
        'middleware' => 'throttle:60,1',
        'preview_mimes' => ['png', 'jpg', 'jpeg', 'webp'],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],
];
