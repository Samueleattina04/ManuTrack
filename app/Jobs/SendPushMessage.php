<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSender;
use App\Support\Push\PushUrgency;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Invia una notifica push ai dispositivi di alcune persone attive.
 *
 * Con $inline = true il lavoro gira subito dopo la risposta HTTP (notifiche
 * urgenti). Se in quel momento l'invio fallisce per un problema temporaneo
 * (rete, servizio push non raggiungibile), i dispositivi non serviti passano
 * alla coda normale: la notifica arriva comunque, al più al giro di cron successivo.
 */
class SendPushMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  list<int>  $userIds
     * @param  list<int>|null  $subscriptionIds  solo questi dispositivi (ripresa dopo un errore)
     */
    public function __construct(
        public array $userIds,
        public PushMessage $message,
        public PushUrgency $urgency = PushUrgency::Normal,
        public bool $inline = false,
        public ?array $subscriptionIds = null,
    ) {}

    public function handle(PushSender $sender): void
    {
        if (! $this->inline) {
            $this->deliver($sender);

            return;
        }

        // La risposta è già partita: l'invio non deve interrompersi se il browser chiude,
        // ma non deve durare all'infinito. Il limite precedente torna al suo posto subito
        // dopo, così chi lavora nello stesso processo (cron, test) non lo eredita.
        ignore_user_abort(true);
        $previous = (int) ini_get('max_execution_time');
        $canLimit = function_exists('set_time_limit');
        if ($canLimit) {
            @set_time_limit(60);
        }
        try {
            $this->deliver($sender);
        } finally {
            if ($canLimit) {
                @set_time_limit($previous);
            }
        }
    }

    private function deliver(PushSender $sender): void
    {
        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $this->userIds)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->when($this->subscriptionIds !== null, fn ($q) => $q->whereIn('id', $this->subscriptionIds))
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $summary = $sender->send($subscriptions, $this->message, $this->urgency->ttl(), $this->urgency->header());
        } catch (Throwable $e) {
            if (! $this->inline) {
                throw $e; // in coda: ci pensano i tentativi della coda
            }
            report($e);
            $this->retryInQueue($subscriptions->modelKeys());

            return;
        }

        if ($this->inline) {
            // Dispositivi non raggiunti per un errore temporaneo (non quelli scaduti, già rimossi)
            $retry = [];
            foreach ($summary['results'] as $result) {
                if (! $result->success && ! $result->expired && $result->temporary()) {
                    $retry[] = $result->subscriptionId;
                }
            }
            $this->retryInQueue(array_values(array_filter(
                $retry,
                fn (int $id) => PushSubscription::query()->whereKey($id)->exists(),
            )));
        }
    }

    /**
     * @param  list<int>  $subscriptionIds
     */
    private function retryInQueue(array $subscriptionIds): void
    {
        if ($subscriptionIds === []) {
            return;
        }

        $job = new self($this->userIds, $this->message, $this->urgency, inline: false, subscriptionIds: $subscriptionIds);

        // Con la coda "sync" il nuovo tentativo sarebbe immediato e ripeterebbe l'errore
        if (config('queue.default') !== 'sync') {
            dispatch($job);
        }
    }
}
