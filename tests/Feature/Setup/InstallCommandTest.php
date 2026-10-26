<?php

use App\Models\SystemVersion;
use App\Models\User;
use App\Support\Setup\InstallState;

it('installa ManuTrack da riga di comando', function () {
    $this->artisan('manutrack:install', [
        '--company' => 'Officine Prova',
        '--name' => 'Mario Prova',
        '--email' => 'Mario@Example.test',
        '--password' => 'Password1234',
        '--palette' => 'ciano',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::query()->sole();
    expect($user->email)->toBe('mario@example.test')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(password_verify('Password1234', $user->password))->toBeTrue()
        ->and(settings('company.name'))->toBe('Officine Prova')
        ->and(settings('company.palette'))->toBe('ciano')
        ->and(SystemVersion::query()->sole()->trigger)->toBe('install')
        ->and(InstallState::hasLock())->toBeTrue();
});

it('genera e salva le chiavi push se richiesto', function () {
    $this->artisan('manutrack:install', [
        '--company' => 'Officine Prova', '--name' => 'Mario', '--email' => 'm@example.test',
        '--password' => 'Password1234', '--vapid' => true, '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->envFile())->toMatch('/^VAPID_PUBLIC_KEY=\S{40,}$/m')
        ->and($this->envFile())->toMatch('/^VAPID_PRIVATE_KEY=\S{20,}$/m');
});

it('rifiuta una seconda installazione', function () {
    installManuTrack();

    $this->artisan('manutrack:install', [
        '--company' => 'Altra', '--name' => 'Altro', '--email' => 'altro@example.test',
        '--password' => 'Password1234', '--no-interaction' => true,
    ])->assertFailed();

    expect(User::query()->count())->toBe(1)
        ->and(settings('company.name'))->toBe('Officine Prova');
});

it('rifiuta di installare se ci sono già utenti, anche senza file di blocco', function () {
    installManuTrack();
    unlink(InstallState::lockPath());
    SystemVersion::query()->delete();

    $this->artisan('manutrack:install', [
        '--company' => 'Altra', '--name' => 'Altro', '--email' => 'altro@example.test',
        '--password' => 'Password1234', '--no-interaction' => true,
    ])->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('valida i dati senza installare nulla', function () {
    $this->artisan('manutrack:install', [
        '--company' => 'Officine', '--name' => 'Mario', '--email' => 'non-una-email',
        '--password' => 'corta', '--no-interaction' => true,
    ])->assertExitCode(2);

    expect(InstallState::isInstalled())->toBeFalse();
});
