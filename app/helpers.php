<?php

use App\Support\AppVersion;
use App\Support\Settings;

if (! function_exists('settings')) {
    /**
     * Legge un'impostazione dell'azienda, oppure restituisce il servizio.
     *
     * @return ($key is null ? Settings : mixed)
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}

if (! function_exists('manutrack_version')) {
    /**
     * Versione del codice in esecuzione (file VERSION).
     */
    function manutrack_version(): string
    {
        return AppVersion::code();
    }
}
