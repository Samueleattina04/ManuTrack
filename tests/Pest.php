<?php

use App\Actions\Setup\InstallApplication;
use App\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

require_once __DIR__.'/Feature/Maintenance/helpers.php';
require_once __DIR__.'/Feature/Inventory/helpers.php';
require_once __DIR__.'/Feature/Preventive/helpers.php';
require_once __DIR__.'/Feature/Plant/helpers.php';
require_once __DIR__.'/Feature/Security/helpers.php';

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

/**
 * Avvia $count processi PHP separati (tests/fixtures/$script), ognuno con la sua
 * connessione al database di prova, che partono tutti nello stesso istante.
 *
 * @return list<string> esito di ogni processo
 */
function raceWorkers(string $script, int $count, array $args): array
{
    $db = config('database.connections.'.config('database.default'));
    $env = array_merge(getenv(), [
        'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'APP_DEBUG' => 'false',
        'DB_CONNECTION' => config('database.default'), 'DB_HOST' => (string) $db['host'], 'DB_PORT' => (string) $db['port'],
        'DB_DATABASE' => (string) $db['database'], 'DB_USERNAME' => (string) $db['username'], 'DB_PASSWORD' => (string) $db['password'], 'DB_URL' => '',
        'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'VAPID_PUBLIC_KEY' => '', 'VAPID_PRIVATE_KEY' => '',
    ]);
    $start = microtime(true) + 1.5; // tempo per avviarsi tutti, poi partono insieme
    $procs = [];
    for ($i = 0; $i < $count; $i++) {
        $cmd = array_merge([PHP_BINARY, base_path('tests/fixtures/'.$script)], array_map('strval', $args), [sprintf('%.4f', $start)]);
        $procs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env) ?: throw new RuntimeException('proc_open');
        $outputs[] = $pipes;
    }
    $results = [];
    foreach ($procs as $i => $proc) {
        $out = trim((string) stream_get_contents($outputs[$i][1]));
        $err = trim((string) stream_get_contents($outputs[$i][2]));
        proc_close($proc);
        $results[] = $out !== '' ? $out : 'errore: '.mb_substr($err, 0, 300);
    }

    return $results;
}
