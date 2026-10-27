<?php

namespace App\Http\Controllers\Push;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * "Invia notifica di prova": a questo dispositivo o a tutti i miei dispositivi.
 * L'invio è immediato (non passa dalla coda) per mostrare subito l'esito.
 */
class PushTestController extends Controller
{
    public function __invoke(Request $request, PushSender $sender): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['nullable', 'string', 'max:2048']]);

        if (! $sender->configured()) {
            return response()->json(['ok' => false, 'message' => __('pwa.push.no_keys')], 409);
        }

        $query = PushSubscription::query()->where('user_id', $request->user()->id);
        if (! empty($data['endpoint'])) {
            $query->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']));
        }
        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) {
            return response()->json(['ok' => false, 'message' => __('pwa.push.no_devices')], 404);
        }

        try {
            $summary = $sender->send($subscriptions, new PushMessage(
                title: __('pwa.push.test_title'),
                body: __('pwa.push.test_body', ['time' => now()->format('H:i:s')]),
                url: route('pwa.diagnostics', absolute: false),
                tag: 'manutrack-test',
                count: 1,
            ), ttl: 300);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => __('pwa.push.send_error', ['message' => $e->getMessage()])], 500);
        }

        $labels = $subscriptions->keyBy('id');

        return response()->json([
            'ok' => $summary['sent'] > 0,
            'message' => trans_choice('pwa.push.test_result', $summary['sent'], ['sent' => $summary['sent'], 'total' => $subscriptions->count()]),
            'sent' => $summary['sent'],
            'expired' => $summary['expired'],
            'failed' => $summary['failed'],
            'results' => array_map(fn ($r) => [
                'device' => $labels->get($r->subscriptionId)?->device_label,
                'ok' => $r->success,
                'expired' => $r->expired,
                'status' => $r->status,
                'reason' => $r->reason,
            ], $summary['results']),
        ]);
    }
}
