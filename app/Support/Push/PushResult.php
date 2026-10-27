<?php

namespace App\Support\Push;

/**
 * Esito dell'invio a un dispositivo.
 */
final class PushResult
{
    public function __construct(
        public readonly int $subscriptionId,
        public readonly bool $success,
        /** Iscrizione non più valida (risposta 404 o 410): va cancellata */
        public readonly bool $expired = false,
        public readonly ?int $status = null,
        public readonly ?string $reason = null,
    ) {}
}
