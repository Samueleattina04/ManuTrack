<?php

use App\Models\User;
use App\Support\Setup\InstallState;

it('prepara un\'installazione di sviluppo con migrate --seed', function () {
    $this->artisan('migrate', ['--seed' => true, '--force' => true])->assertSuccessful();

    expect(InstallState::isInstalled())->toBeTrue()
        ->and(User::query()->where('email', 'admin@example.com')->exists())->toBeTrue();

    $this->get('/')->assertRedirect(route('login'));
    $this->actingAs(User::query()->where('email', 'admin@example.com')->sole())->get('/')->assertOk();
    // Ripetibile senza errori
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
});
