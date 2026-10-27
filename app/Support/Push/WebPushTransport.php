<?php

namespace App\Support\Push;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

class WebPushTransport implements PushTransport
{
    public function send(iterable $subscriptions, string $payload, int $ttl): array
    {
        $publicKey = (string) config('manutrack.webpush.public_key');
        $privateKey = (string) config('manutrack.webpush.private_key');
        if ($publicKey === '' || $privateKey === '') {
            throw new RuntimeException(__('pwa.push.no_keys'));
        }

        // Senza bcmath o gmp la libreria emette solo un avviso di lentezza (E_USER_NOTICE):
        // sugli hosting condivisi capita spesso e non deve bloccare l'invio.
        set_error_handler(fn (int $no, string $msg) => $no === E_USER_NOTICE && str_contains($msg, 'GMP or BCMath'), E_USER_NOTICE);
        try {
            return $this->dispatch($subscriptions, $payload, $ttl, $publicKey, $privateKey);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param  iterable<PushSubscription>  $subscriptions
     * @return list<PushResult>
     */
    private function dispatch(iterable $subscriptions, string $payload, int $ttl, string $publicKey, string $privateKey): array
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) (config('manutrack.webpush.subject') ?: config('app.url')),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ], ['TTL' => $ttl, 'urgency' => 'high'], 20);
        $webPush->setReuseVAPIDHeaders(true);

        $byEndpoint = [];
        foreach ($subscriptions as $sub) {
            $byEndpoint[$sub->endpoint] = $sub->id;
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
                'contentEncoding' => $sub->content_encoding,
            ]), $payload);
        }

        $results = [];
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            $status = $report->getResponse()?->getStatusCode();
            $results[] = new PushResult(
                subscriptionId: (int) ($byEndpoint[$endpoint] ?? 0),
                success: $report->isSuccess(),
                expired: $report->isSubscriptionExpired(),
                status: $status,
                reason: $report->isSuccess() ? null : mb_strimwidth($report->getReason(), 0, 250, '…'),
            );
        }

        return $results;
    }
}
