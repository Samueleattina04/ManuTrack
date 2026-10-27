<?php

namespace Tests\Support;

use App\Support\Push\PushResult;
use App\Support\Push\PushTransport;

/**
 * Trasporto push finto: registra gli invii e risponde con l'esito scelto.
 */
class RecordingPushTransport implements PushTransport
{
    /** @var list<array{payload: array<string, mixed>, ttl: int, urgency: string, ids: list<int>}> */
    public array $sent = [];

    /** @var (callable(int): PushResult)|null */
    public $respond = null;

    public function send(iterable $subscriptions, string $payload, int $ttl, string $urgency = 'normal'): array
    {
        $ids = [];
        $results = [];
        foreach ($subscriptions as $sub) {
            $ids[] = $sub->id;
            $results[] = $this->respond ? ($this->respond)($sub->id) : new PushResult($sub->id, true, status: 201);
        }
        $this->sent[] = ['payload' => json_decode($payload, true), 'ttl' => $ttl, 'urgency' => $urgency, 'ids' => $ids];

        return $results;
    }
}
