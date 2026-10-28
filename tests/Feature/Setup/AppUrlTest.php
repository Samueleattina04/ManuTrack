<?php

use App\Support\Setup\AppUrlCheck;
use App\Support\Setup\InstallState;
use App\Support\Setup\SystemStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/*
 * APP_URL: da qui nascono i link nelle email (nuova password, inviti) e nei QR. L'installazione
 * dal browser non parte se è sbagliato; la pagina Sistema lo segnala.
 */

function installFormFor(string $email): array
{
    return ['token' => 'codice-di-prova', 'company' => 'Officine Web', 'name' => 'Laura Prova', 'email' => $email,
        'password' => 'Password1234', 'password_confirmation' => 'Password1234', 'palette' => 'rame', 'vapid' => '0'];
}

it('riconosce un APP_URL giusto, mancante, senza https in produzione, locale o di un altro dominio', function () {
    $request = fn (string $url) => Request::create($url);

    config(['app.url' => 'http://localhost']);
    expect(AppUrlCheck::problems($request('http://localhost/install')))->toBe([]);

    config(['app.url' => '']);
    expect(AppUrlCheck::problems())->toHaveKey('missing');

    config(['app.url' => 'https://manutenzione.example.com']);
    expect(AppUrlCheck::problems($request('https://manutenzione.example.com/install')))->toBe([])
        ->and(AppUrlCheck::problems($request('https://altro.example.net/install')))->toHaveKey('host');

    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'http://manutenzione.example.com']);
    expect(AppUrlCheck::problems($request('http://manutenzione.example.com/')))->toHaveKey('https');
    config(['app.url' => 'https://localhost']);
    expect(AppUrlCheck::problems())->toHaveKey('local');
    config(['app.url' => 'https://manutenzione.example.com']);
    expect(AppUrlCheck::problems($request('http://manutenzione.example.com/')))->toHaveKey('request_https')
        ->and(AppUrlCheck::problems($request('https://manutenzione.example.com/')))->toBe([]);
    app()->detectEnvironment(fn () => 'testing');
});

it('l\'installazione dal browser non parte con APP_URL di un altro dominio, e lo spiega', function () {
    config(['manutrack.install_token' => 'codice-di-prova', 'app.url' => 'https://manutenzione.example.com']);

    $this->get('/install')->assertOk()->assertSee(__('install.app_url.label'));
    $this->from('/install')->post('/install', installFormFor('laura@example.test'))
        ->assertRedirect('/install')
        ->assertSessionHasErrors(['install' => __('install.app_url.host', ['url' => 'https://manutenzione.example.com', 'host' => 'localhost'])]);
    expect(InstallState::isInstalled())->toBeFalse()->and(Schema::hasTable('users'))->toBeFalse();

    // Corretto APP_URL, l'installazione parte
    config(['app.url' => 'http://localhost']);
    file_put_contents($this->sandbox.'/.env', "APP_NAME=ManuTrack\nMANUTRACK_INSTALL_TOKEN=codice-di-prova\n");
    $this->post('/install', installFormFor('laura@example.test'))->assertOk()->assertSee(__('install.done_title'));
});

it('la pagina Sistema avvisa se APP_URL è sbagliato', function () {
    $admin = installManuTrack();
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('system'))->assertOk()->assertDontSee(__('install.app_url.system_title'));
    config(['app.url' => 'https://manutenzione.example.com']);
    $this->get(route('system'))->assertOk()->assertSee(__('install.app_url.system_title'))
        ->assertSee(__('install.app_url.host', ['url' => 'https://manutenzione.example.com', 'host' => 'localhost']));
});

it('la pagina Sistema controlla il blocco dell\'installazione e il contatto delle chiavi push', function () {
    $admin = installManuTrack();
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    $status = app(SystemStatus::class);
    $row = fn (string $key) => collect($status->checks())->firstWhere('key', $key);

    expect($row('install-lock')['status'])->toBe('ok');
    config(['manutrack.install_token' => 'rimasto-nel-env']);
    expect($row('install-lock')['status'])->toBe('warning');
    config(['manutrack.install_token' => '']);

    config(['manutrack.webpush.public_key' => 'pub', 'manutrack.webpush.private_key' => 'priv', 'manutrack.webpush.subject' => 'mailto:manutrack@example.com']);
    expect($row('vapid-subject')['status'])->toBe('warning');
    $this->get(route('system'))->assertSee(__('system.checks.vapid_subject'));
    config(['manutrack.webpush.subject' => 'mailto:assistenza@officine-prova.it']);
    expect($row('vapid-subject')['status'])->toBe('ok');
});
