<?php

use App\Models\AuditLog;
use App\Models\PushSubscription;
use App\Support\Access\Access;
use App\Support\Push\PushMessage;
use App\Support\Push\PushResult;
use App\Support\Push\PushSender;
use App\Support\Push\PushTransport;
use App\Support\Push\WebPushTransport;
use Minishlink\WebPush\VAPID;

/** Chiavi di un "browser" finto: p256dh e auth come le genera un dispositivo vero */
function fakeBrowserKeys(): array
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $d = openssl_pkey_get_details($key)['ec'];
    $b64 = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

    return ['p256dh' => $b64("\x04".str_pad($d['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['y'], 32, "\0", STR_PAD_LEFT)), 'auth' => $b64(random_bytes(16))];
}

function subscriptionPayload(string $endpoint = 'https://web.push.apple.com/QGuQyavXutnMH_8ZjlxIkW4m'): array
{
    return ['endpoint' => $endpoint, 'keys' => fakeBrowserKeys(), 'contentEncoding' => 'aes128gcm'];
}

beforeEach(function () {
    $this->admin = installManuTrack();
    $keys = VAPID::createVapidKeys();
    config(['manutrack.webpush.public_key' => $keys['publicKey'], 'manutrack.webpush.private_key' => $keys['privateKey'], 'manutrack.webpush.subject' => 'mailto:test@example.test']);
});

it('registra un dispositivo, riconosce iPhone e non duplica', function () {
    $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';
    $payload = subscriptionPayload();

    $this->actingAs($this->admin)->withHeader('User-Agent', $ua)->postJson('/push/iscrizioni', $payload)->assertCreated();
    $this->actingAs($this->admin)->withHeader('User-Agent', $ua)->postJson('/push/iscrizioni', $payload)->assertOk();

    $sub = PushSubscription::query()->sole();
    expect($sub->device_label)->toBe('iPhone · Safari')
        ->and($sub->platform)->toBe('ios')
        ->and($sub->service())->toBe('Apple')
        ->and($sub->endpoint_hash)->toBe(hash('sha256', $payload['endpoint']))
        ->and(AuditLog::query()->where('action', 'push.device_added')->count())->toBe(1);
});

it('una persona può avere più dispositivi', function () {
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', subscriptionPayload('https://web.push.apple.com/uno'))->assertCreated();
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', subscriptionPayload('https://fcm.googleapis.com/fcm/send/due'))->assertCreated();

    expect(PushSubscription::query()->where('user_id', $this->admin->id)->count())->toBe(2);
});

it('accetta solo indirizzi https e dati completi', function () {
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', ['endpoint' => 'http://insicuro.example/x', 'keys' => fakeBrowserKeys()])->assertUnprocessable();
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', ['endpoint' => 'https://web.push.apple.com/x'])->assertUnprocessable();
    auth()->logout();
    $this->postJson('/push/iscrizioni', subscriptionPayload())->assertUnauthorized();
});

it('rimuove il proprio dispositivo ma non quello degli altri', function () {
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', subscriptionPayload('https://web.push.apple.com/mio'));
    $other = userWithRole(Access::TECHNICIAN);
    $this->actingAs($other)->postJson('/push/iscrizioni', subscriptionPayload('https://web.push.apple.com/altrui'));
    $altrui = PushSubscription::query()->where('user_id', $other->id)->sole();

    $this->actingAs($this->admin)->deleteJson(route('push.devices.remove', $altrui))->assertNotFound();
    $this->actingAs($this->admin)->deleteJson('/push/iscrizioni', ['endpoint' => 'https://web.push.apple.com/altrui'])->assertOk()->assertJson(['deleted' => 0]);
    $this->actingAs($this->admin)->deleteJson('/push/iscrizioni', ['endpoint' => 'https://web.push.apple.com/mio'])->assertJson(['deleted' => 1]);

    expect(PushSubscription::query()->count())->toBe(1);
});

it('il service worker rinnova l\'iscrizione solo se conosce quella precedente', function () {
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', subscriptionPayload('https://fcm.googleapis.com/fcm/send/vecchia'));
    auth()->logout();

    $new = subscriptionPayload('https://fcm.googleapis.com/fcm/send/nuova');
    $this->postJson('/push/rinnova', ['old_endpoint' => 'https://fcm.googleapis.com/fcm/send/inventata', 'subscription' => $new])->assertNotFound();
    $this->postJson('/push/rinnova', ['old_endpoint' => 'https://fcm.googleapis.com/fcm/send/vecchia', 'subscription' => $new])->assertOk();

    expect(PushSubscription::query()->sole()->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/nuova');
});

it('la notifica di prova va ai miei dispositivi e pulisce quelli scaduti', function () {
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', subscriptionPayload('https://web.push.apple.com/attivo'));
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', subscriptionPayload('https://fcm.googleapis.com/fcm/send/scaduto'));
    [$ok, $expired] = PushSubscription::query()->orderBy('id')->get()->all();

    $this->app->instance(PushTransport::class, new class($ok->id, $expired->id) implements PushTransport
    {
        public array $payloads = [];

        public function __construct(private int $ok, private int $gone) {}

        public function send(iterable $subscriptions, string $payload, int $ttl): array
        {
            $this->payloads[] = json_decode($payload, true);

            return [new PushResult($this->ok, true, status: 201), new PushResult($this->gone, false, true, 410, 'Gone')];
        }
    });

    $this->actingAs($this->admin)->postJson('/push/prova')
        ->assertOk()->assertJson(['ok' => true, 'sent' => 1, 'expired' => 1]);

    expect(PushSubscription::query()->pluck('id')->all())->toBe([$ok->id])
        ->and($ok->fresh()->last_success_at)->not->toBeNull();

    $sent = app(PushTransport::class)->payloads[0];
    expect($sent['title'])->toBe(__('pwa.push.test_title'))
        ->and($sent['url'])->toBe('/notifiche/diagnostica')
        ->and($sent['tag'])->toBe('manutrack-test');
});

it('senza chiavi VAPID o senza dispositivi spiega cosa manca', function () {
    $this->actingAs($this->admin)->postJson('/push/prova')->assertNotFound()->assertJson(['message' => __('pwa.push.no_devices')]);

    config(['manutrack.webpush.public_key' => null]);
    $this->actingAs($this->admin)->postJson('/push/prova')->assertStatus(409)->assertJson(['message' => __('pwa.push.no_keys')]);
});

it('dopo troppi errori di fila un dispositivo viene tolto', function () {
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', subscriptionPayload());
    $sub = PushSubscription::query()->sole();
    $this->app->instance(PushTransport::class, new class($sub->id) implements PushTransport
    {
        public function __construct(private int $id) {}

        public function send(iterable $subscriptions, string $payload, int $ttl): array
        {
            return [new PushResult($this->id, false, false, 500, 'errore temporaneo')];
        }
    });

    $sender = app(PushSender::class);
    foreach (range(1, PushSender::MAX_FAILURES - 1) as $i) {
        $sender->toUser($this->admin, new PushMessage('t', 'b'));
    }
    expect($sub->fresh()->failed_count)->toBe(PushSender::MAX_FAILURES - 1)->and($sub->fresh()->last_error)->toBe('errore temporaneo');

    $sender->toUser($this->admin, new PushMessage('t', 'b'));
    expect(PushSubscription::query()->count())->toBe(0);
});

it('cifra e invia davvero con VAPID a un servizio push (finto, locale)', function () {
    $port = random_int(18100, 18900);
    $log = $this->sandbox.'/push.log';
    $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", base_path('tests/fixtures/fake-push-service.php')], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['FAKE_PUSH_LOG' => $log]);
    usleep(400_000);

    try {
        foreach (['ok', 'gone'] as $kind) {
            PushSubscription::query()->create([
                'user_id' => $this->admin->id,
                'endpoint' => "http://127.0.0.1:{$port}/{$kind}/abc",
                'endpoint_hash' => hash('sha256', $kind),
                'public_key' => ($k = fakeBrowserKeys())['p256dh'],
                'auth_token' => $k['auth'],
            ]);
        }

        $this->app->bind(PushTransport::class, WebPushTransport::class);
        $summary = app(PushSender::class)->toUser($this->admin, new PushMessage('Ordine assegnato', 'OdL-2026-00042 · Pressa', '/ordini/42', 'odl-42', 3));

        expect($summary['sent'])->toBe(1)->and($summary['expired'])->toBe(1)
            ->and(PushSubscription::query()->count())->toBe(1);

        $requests = array_map(fn ($l) => json_decode($l, true), file($log, FILE_IGNORE_NEW_LINES));
        expect($requests)->toHaveCount(2)
            ->and($requests[0]['authorization'])->toStartWith('vapid t=')
            ->and($requests[0]['encoding'])->toBe('aes128gcm')
            ->and($requests[0]['bytes'])->toBeGreaterThan(100);
    } finally {
        proc_terminate($process);
    }
})->skip(fn () => ! function_exists('proc_open'), 'proc_open non disponibile');
