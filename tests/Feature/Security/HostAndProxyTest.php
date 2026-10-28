<?php

use App\Livewire\Users\Index;
use App\Models\Invitation;
use App\Notifications\InvitationNotification;
use App\Support\Access\Access;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/*
 * Controllo di sicurezza: indirizzi nelle email e proxy fidati (X-Forwarded-*).
 */

beforeEach(function () {
    $this->admin = installManuTrack();
    Notification::fake();
});

afterEach(function () {
    URL::forceRootUrl(null);
});

it('il link per la nuova password punta sempre ad APP_URL, qualunque Host o X-Forwarded-Host arrivi', function () {
    $this->withHeaders(['X-Forwarded-Host' => 'attaccante.example.net', 'Host' => 'altro.example.net'])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post('/forgot-password', ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, ResetPassword::class, function (ResetPassword $n) {
        $url = $n->toMail($this->admin)->actionUrl;
        expect(parse_url($url, PHP_URL_HOST))->toBe('localhost');

        return true;
    });
});

it('anche un proxy locale non sceglie l\'host dei link nelle email', function () {
    $this->withHeaders(['X-Forwarded-Host' => 'attaccante.example.net', 'X-Forwarded-Proto' => 'https'])
        ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->post('/forgot-password', ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, ResetPassword::class, fn (ResetPassword $n) => parse_url($n->toMail($this->admin)->actionUrl, PHP_URL_HOST) === 'localhost');
});

it('il link dell\'invito punta ad APP_URL', function () {
    $this->actingAs($this->admin)->withHeaders(['X-Forwarded-Host' => 'attaccante.example.net'])->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
    Livewire\Livewire::test(Index::class)->set('inviteEmail', 'nuovo@example.test')->set('inviteRole', Access::TECHNICIAN)->call('invite');

    Notification::assertSentTo(Invitation::query()->sole(), InvitationNotification::class, function (InvitationNotification $n, $channels, $notifiable) {
        return parse_url($n->toMail($notifiable)->actionUrl, PHP_URL_HOST) === 'localhost';
    });
});

it('da internet X-Forwarded-For non cambia l\'indirizzo: né per il registro né per i limiti di accesso', function () {
    $ips = [];
    foreach (range(1, 6) as $i) {
        $response = $this->withHeaders(['X-Forwarded-For' => '198.51.100.'.$i])->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post('/login', ['email' => $this->admin->email, 'password' => 'sbagliata']);
        $ips[] = app('request')->ip();
    }
    // Il sesto tentativo è bloccato: l'indirizzo è sempre quello vero
    $response->assertStatus(429);
    expect(array_unique($ips))->toBe(['203.0.113.9']);
});

it('un proxy sulla stessa macchina (o configurato) passa l\'indirizzo vero del client', function () {
    $this->withHeaders(['X-Forwarded-For' => '198.51.100.7'])->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->post('/login', ['email' => $this->admin->email, 'password' => 'sbagliata']);
    expect(app('request')->ip())->toBe('198.51.100.7');

    config(['manutrack.trusted_proxies' => '203.0.113.0/24']);
    $this->withHeaders(['X-Forwarded-For' => '198.51.100.8'])->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
        ->post('/login', ['email' => $this->admin->email, 'password' => 'sbagliata']);
    expect(app('request')->ip())->toBe('198.51.100.8');
});
