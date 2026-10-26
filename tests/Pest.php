<?php

use App\Actions\Setup\InstallApplication;
use App\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * Installa ManuTrack nel database di prova e restituisce l'amministratore.
 */
function installManuTrack(array $overrides = []): User
{
    $result = app(InstallApplication::class)->handle(array_merge([
        'company' => 'Officine Prova',
        'name' => 'Amministratore Prova',
        'email' => 'admin@example.test',
        'password' => 'Password1234',
        'palette' => 'rame',
        'vapid' => false,
    ], $overrides));

    return User::query()->findOrFail($result['user_id']);
}

/**
 * Crea una persona attiva con il ruolo indicato (richiede ruoli già sincronizzati).
 */
function userWithRole(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->syncRoles([$role]);

    return $user;
}
