<?php

use App\Models\User;
use App\Support\Setup\InstallState;

function installForm(array $overrides = []): array
{
    return array_merge([
        'token' => 'codice-segreto-di-prova',
        'company' => 'Officine Web',
        'name' => 'Laura Prova',
        'email' => 'laura@example.test',
        'password' => 'Password1234',
        'password_confirmation' => 'Password1234',
        'palette' => 'rame',
        'vapid' => '0',
    ], $overrides);
}

it('porta all\'installazione finché l\'app non è installata', function () {
    $this->get('/')->assertRedirect(route('install.show'));
    $this->get('/install')->assertOk()->assertSee(__('install.heading'));
});

it('non mostra il modulo se il codice non è configurato', function () {
    config(['manutrack.install_token' => '']);

    $this->get('/install')->assertOk()
        ->assertSee(__('install.token_missing'))
        ->assertDontSee('name="password"', false);
});

it('rifiuta un codice sbagliato', function () {
    config(['manutrack.install_token' => 'codice-segreto-di-prova']);

    $this->from('/install')->post('/install', installForm(['token' => 'sbagliato']))
        ->assertRedirect('/install')
        ->assertSessionHasErrors('token');

    expect(InstallState::isInstalled())->toBeFalse();
});

it('installa, cancella il codice da .env e poi si disattiva', function () {
    config(['manutrack.install_token' => 'codice-segreto-di-prova']);
    file_put_contents($this->sandbox.'/.env', "APP_NAME=ManuTrack\nMANUTRACK_INSTALL_TOKEN=codice-segreto-di-prova\n");

    $this->post('/install', installForm())
        ->assertOk()
        ->assertSee(__('install.done_title'));

    expect(User::query()->sole()->email)->toBe('laura@example.test')
        ->and(InstallState::hasLock())->toBeTrue()
        ->and($this->envFile())->toContain("MANUTRACK_INSTALL_TOKEN=\n");

    // Dopo l'installazione le pagine dell'installazione non esistono più
    $this->get('/install')->assertNotFound();
    $this->post('/install', installForm(['email' => 'intruso@example.test']))->assertNotFound();
    expect(User::query()->count())->toBe(1);
    $this->get('/')->assertOk();
});

it('resta disattivata se il file di blocco viene cancellato', function () {
    installManuTrack();
    unlink(InstallState::lockPath());
    config(['manutrack.install_token' => 'codice-segreto-di-prova']);

    $this->get('/install')->assertNotFound();
    $this->post('/install', installForm())->assertNotFound();

    // Il file di blocco viene ricreato
    expect(InstallState::hasLock())->toBeTrue();
});

it('valida il modulo', function () {
    config(['manutrack.install_token' => 'codice-segreto-di-prova']);

    $this->from('/install')->post('/install', installForm([
        'email' => 'sbagliata', 'password' => 'corta', 'password_confirmation' => 'diversa', 'palette' => 'arcobaleno',
    ]))->assertSessionHasErrors(['email', 'password', 'palette']);
});
