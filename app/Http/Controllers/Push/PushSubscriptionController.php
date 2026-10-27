<?php

namespace App\Http\Controllers\Push;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Support\Audit\Audit;
use App\Support\Push\DeviceLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Iscrizioni alle notifiche push del dispositivo in uso.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $subscription = $this->save($request, $data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'], $data['contentEncoding'] ?? 'aes128gcm');

        return response()->json(['ok' => true, 'id' => $subscription->id, 'device' => $subscription->device_label], $subscription->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        $deleted = PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
            ->delete();

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    /**
     * Rimuove un dispositivo dall'elenco (pagina diagnostica).
     */
    public function remove(Request $request, PushSubscription $subscription): JsonResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        $subscription->delete();
        Audit::log('push.device_removed', $subscription, $subscription->device_label);

        return response()->json(['ok' => true]);
    }

    /**
     * Chiamata dal service worker quando il servizio push cambia l'iscrizione.
     * Non c'è il token CSRF: la richiesta vale solo se l'iscrizione precedente esiste
     * (l'indirizzo è segreto e lo conosce solo il dispositivo).
     */
    public function renew(Request $request): JsonResponse
    {
        $request->validate([
            'old_endpoint' => ['required', 'string', 'max:2048'],
            'subscription.endpoint' => ['required', 'url:https', 'max:2048'],
            'subscription.keys.p256dh' => ['required', 'string', 'max:255'],
            'subscription.keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $old = PushSubscription::findByEndpoint((string) $request->input('old_endpoint'));
        abort_unless($old !== null, 404);

        $endpoint = (string) $request->input('subscription.endpoint');
        $existing = PushSubscription::findByEndpoint($endpoint);
        if ($existing && ! $existing->is($old)) {
            $existing->delete();
        }

        $old->forceFill([
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'public_key' => (string) $request->input('subscription.keys.p256dh'),
            'auth_token' => (string) $request->input('subscription.keys.auth'),
            'failed_count' => 0,
        ])->save();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding?: string}
     */
    private function validated(Request $request): array
    {
        /** @var array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding?: string} */
        return $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);
    }

    private function save(Request $request, string $endpoint, string $p256dh, string $auth, string $encoding): PushSubscription
    {
        $device = DeviceLabel::from($request->userAgent());
        $hash = PushSubscription::hashEndpoint($endpoint);

        // Lo stesso dispositivo può passare a un'altra persona (es. telefono di reparto)
        $subscription = PushSubscription::query()->firstOrNew(['endpoint_hash' => $hash]);
        $isNew = ! $subscription->exists || $subscription->user_id !== $request->user()->id;

        $subscription->forceFill([
            'user_id' => $request->user()->id,
            'endpoint' => $endpoint,
            'public_key' => $p256dh,
            'auth_token' => $auth,
            'content_encoding' => $encoding,
            'device_label' => $device['label'],
            'platform' => $device['platform'],
            'user_agent' => mb_strimwidth((string) $request->userAgent(), 0, 255),
            'last_used_at' => now(),
            'failed_count' => 0,
            'last_error' => null,
        ])->save();

        if ($isNew) {
            Audit::log('push.device_added', $subscription, $device['label']);
        }

        return $subscription;
    }
}
