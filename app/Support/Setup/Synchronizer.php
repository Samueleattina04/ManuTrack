<?php

namespace App\Support\Setup;

/**
 * Allinea dati di sistema (impostazioni predefinite, ruoli, permessi…) allo stato
 * atteso dalla versione del codice. Deve essere idempotente: viene eseguito a
 * ogni installazione e a ogni aggiornamento.
 */
interface Synchronizer
{
    public function sync(): void;
}
