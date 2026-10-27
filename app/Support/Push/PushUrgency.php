<?php

namespace App\Support\Push;

/**
 * Quanto è urgente una notifica push.
 *
 * - Immediata: guasti critici, linee ferme. Parte subito dopo la risposta alla
 *   persona che ha fatto l'azione, senza aspettare il cron della coda, e chiede
 *   ai servizi push la consegna ad alta priorità (sveglia il telefono anche
 *   in risparmio energetico).
 * - Normale: tutto il resto. Passa dalla coda, che il cron svuota ogni minuto.
 */
enum PushUrgency: string
{
    case Immediate = 'immediata';
    case Normal = 'normale';

    /** Valore dell'intestazione Urgency del protocollo Web Push */
    public function header(): string
    {
        return $this === self::Immediate ? 'high' : 'normal';
    }

    /** Per quanto tempo il servizio push conserva la notifica se il telefono è spento */
    public function ttl(): int
    {
        return $this === self::Immediate ? 6 * 3600 : 24 * 3600;
    }
}
