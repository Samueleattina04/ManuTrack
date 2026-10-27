<?php

namespace App\Support\Push;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Invia notifiche push a uno o più dispositivi e tiene pulito l'elenco:
 * le iscrizioni scadute (404/410) vengono cancellate, quelle che falliscono
 * troppe volte di fila anche.
 */
class PushSender
{
    public const MAX_FAILURES = 5;

    public function __construct(private PushTransport $transport) {}

    public function configured(): bool
    {
        return filled(config('manutrack.webpush.public_key')) && filled(config('manutrack.webpush.private_key'));
    }

    /**
     * @return array{sent: int, expired: int, failed: int, results: list<PushResult>}
     */
    public function toUser(User $user, PushMessage $message, int $ttl = 3600): array
    {
        return $this->send(PushSubscription::query()->where('user_id', $user->id)->get(), $message, $ttl);
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @return array{sent: int, expired: int, failed: int, results: list<PushResult>}
     */
    public function send(Collection $subscriptions, PushMessage $message, int $ttl = 3600): array
    {
        if ($subscriptions->isEmpty()) {
            return ['sent' => 0, 'expired' => 0, 'failed' => 0, 'results' => []];
        }

        $results = $this->transport->send($subscriptions, $message->toJson(), $ttl);
        $byId = $subscriptions->keyBy('id');
        $summary = ['sent' => 0, 'expired' => 0, 'failed' => 0, 'results' => $results];

        foreach ($results as $result) {
            /** @var PushSubscription|null $sub */
            $sub = $byId->get($result->subscriptionId);
            if (! $sub) {
                continue;
            }

            if ($result->success) {
                $summary['sent']++;
                $sub->forceFill(['last_used_at' => now(), 'last_success_at' => now(), 'failed_count' => 0, 'last_error' => null])->save();
            } elseif ($result->expired) {
                $summary['expired']++;
                $sub->delete();
            } else {
                $summary['failed']++;
                $failures = $sub->failed_count + 1;
                if ($failures >= self::MAX_FAILURES) {
                    $sub->delete();
                } else {
                    $sub->forceFill(['failed_count' => $failures, 'last_error' => $result->reason])->save();
                }
            }
        }

        return $summary;
    }
}
