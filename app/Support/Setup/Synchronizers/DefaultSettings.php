<?php

namespace App\Support\Setup\Synchronizers;

use App\Support\Settings;
use App\Support\Setup\Synchronizer;

/**
 * Crea le impostazioni mancanti con i valori presi da .env, senza toccare quelle
 * già modificate dall'azienda.
 */
class DefaultSettings implements Synchronizer
{
    public function __construct(private Settings $settings) {}

    public function sync(): void
    {
        $this->settings->ensure($this->settings->defaults());
    }
}
