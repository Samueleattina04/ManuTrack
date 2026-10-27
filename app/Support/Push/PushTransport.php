<?php

namespace App\Support\Push;

use App\Models\PushSubscription;

/**
 * Invio effettivo ai servizi push. L'implementazione reale usa minishlink/web-push;
 * nei test se ne usa una finta.
 */
interface PushTransport
{
    /**
     * @param  iterable<PushSubscription>  $subscriptions
     * @return list<PushResult>
     */
    public function send(iterable $subscriptions, string $payload, int $ttl): array;
}
