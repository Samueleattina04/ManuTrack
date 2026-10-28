<?php

use App\Models\PushSubscription;

/*
 * Controllo di sicurezza (4, SSRF): il server invia richieste agli indirizzi delle iscrizioni
 * push, quindi si accettano solo quelli dei servizi push dei browser.
 */

beforeEach(function () {
    $this->admin = installManuTrack();
});

function browserKeys(): array
{
    // Valori finti: la validazione chiede solo testi; l'invio vero è provato in tests/Feature/Pwa
    return ['p256dh' => str_repeat('A', 87), 'auth' => str_repeat('B', 22)];
}

it('rifiuta indirizzi che non sono di un servizio push: servizi interni, IP, porte, altri siti', function (string $endpoint) {
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', ['endpoint' => $endpoint, 'keys' => browserKeys()])->assertUnprocessable();
    expect(PushSubscription::query()->count())->toBe(0);
})->with([
    'localhost' => ['https://localhost/admin'],
    'IP interno' => ['https://169.254.169.254/latest/meta-data'],
    'IP IPv6' => ['https://[::1]/x'],
    'altro sito' => ['https://attaccante.example.net/raccogli'],
    'nome che finge' => ['https://fcm.googleapis.com.attaccante.example.net/x'],
    'porta' => ['https://fcm.googleapis.com:8443/fcm/send/x'],
    'credenziali' => ['https://utente@fcm.googleapis.com/fcm/send/x'],
]);

it('accetta i servizi push di Chrome, Firefox, Safari ed Edge', function (string $endpoint) {
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', ['endpoint' => $endpoint, 'keys' => browserKeys()])->assertSuccessful();
})->with([
    'Chrome' => ['https://fcm.googleapis.com/fcm/send/abc123'],
    'Chrome (indirizzo nuovo)' => ['https://fcm.googleapis.com/wp/abc123'],
    'Android vecchio' => ['https://android.googleapis.com/gcm/send/abc123'],
    'Firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/abc'],
    'Safari' => ['https://web.push.apple.com/QGuQyavXutnMH_8ZjlxIkW4m'],
    'Edge' => ['https://wns2-par02p.notify.windows.com/w/?token=abc'],
    'Edge (altro nodo)' => ['https://db5p.notify.windows.com/w/?token=abc'],
]);

it('anche il rinnovo dal service worker non può puntare altrove', function () {
    PushSubscription::query()->forceCreate(['user_id' => $this->admin->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/vecchia', 'endpoint_hash' => PushSubscription::hashEndpoint('https://fcm.googleapis.com/fcm/send/vecchia'), 'public_key' => 'k', 'auth_token' => 'a', 'content_encoding' => 'aes128gcm']);

    $this->postJson('/push/rinnova', ['old_endpoint' => 'https://fcm.googleapis.com/fcm/send/vecchia', 'subscription' => ['endpoint' => 'https://127.0.0.1/interno', 'keys' => browserKeys()]])->assertUnprocessable();
    expect(PushSubscription::query()->sole()->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/vecchia');
});

it('un\'iscrizione rifiutata ha un messaggio comprensibile, mostrato dalla diagnostica', function () {
    $this->actingAs($this->admin)->postJson('/push/iscrizioni', ['endpoint' => 'https://push.servizio-sconosciuto.example/x', 'keys' => browserKeys()])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('pwa.push.endpoint_not_allowed'));

    // La pagina passa alla diagnostica l'elenco dei servizi ammessi e i messaggi per ogni errore
    $this->get(route('pwa.diagnostics'))->assertOk()
        ->assertSee('push.apple.com', false)->assertSee('fcm.googleapis.com', false)
        ->assertSee('push.services.mozilla.com', false)->assertSee('notify.windows.com', false)
        ->assertSee(__('pwa.diag.checks.push_service'), false);
    expect(__('pwa.diag.failures.service_not_allowed'))->toContain(':host');
});
