<?php

use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAssetsSeeder;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMaintenanceSeeder;
use Database\Seeders\DemoPreventiveSeeder;

/** Account dimostrativi creati dal seeder di sviluppo */
const DEMO_EMAILS = ['operatore@example.com', 'responsabile@example.com', 'tecnico1@example.com', 'tecnico2@example.com', 'magazzino@example.com', 'admin@example.com'];

function installForm2(): array
{
    return [
        'token' => 'codice-di-prova', 'company' => 'Officine Web', 'name' => 'Laura Prova', 'email' => 'laura@cliente.test',
        'password' => 'Password1234', 'password_confirmation' => 'Password1234', 'palette' => 'rame', 'vapid' => '0',
    ];
}

it('manutrack:install crea solo l\'amministratore, nessun account né dato dimostrativo', function () {
    $this->artisan('manutrack:install', [
        '--company' => 'Officine Prova', '--name' => 'Mario Prova', '--email' => 'mario@cliente.test',
        '--password' => 'Password1234', '--no-interaction' => true,
    ])->assertSuccessful();

    expect(User::query()->pluck('email')->all())->toBe(['mario@cliente.test'])
        ->and(User::query()->whereIn('email', DEMO_EMAILS)->exists())->toBeFalse()
        ->and(Asset::query()->count())->toBe(0)
        ->and(WorkOrder::query()->count())->toBe(0)
        ->and(Part::query()->count())->toBe(0);
});

it('l\'installazione dal browser crea solo l\'amministratore', function () {
    config(['manutrack.install_token' => 'codice-di-prova']);
    file_put_contents($this->sandbox.'/.env', "APP_NAME=ManuTrack\nMANUTRACK_INSTALL_TOKEN=codice-di-prova\n");

    $this->post('/install', installForm2())->assertOk();

    expect(User::query()->pluck('email')->all())->toBe(['laura@cliente.test'])
        ->and(Asset::query()->count())->toBe(0);
});

it('i seeder dimostrativi non creano nulla in produzione', function () {
    installManuTrack(['email' => 'admin@cliente.test']);
    $this->app['env'] = 'production';
    config(['manutrack.demo' => false]);

    // Chiamati direttamente: con "db:seed" Laravel in produzione chiederebbe conferma
    app(DemoAssetsSeeder::class)->run();
    app(DemoMaintenanceSeeder::class)->run();
    app(DemoInventorySeeder::class)->run();
    app(DemoPreventiveSeeder::class)->run();
    app(DatabaseSeeder::class)->run();

    expect(User::query()->whereIn('email', DEMO_EMAILS)->exists())->toBeFalse()
        ->and(Asset::query()->count())->toBe(0)
        ->and(Part::query()->count())->toBe(0);
});

it('in sviluppo il seeder crea gli account dimostrativi', function () {
    $this->artisan('migrate', ['--seed' => true, '--force' => true])->assertSuccessful();
    expect(User::query()->whereIn('email', DEMO_EMAILS)->count())->toBe(6)
        ->and(Part::query()->count())->toBeGreaterThan(0)
        ->and(MaintenancePlan::query()->count())->toBeGreaterThan(0);
});

it('la pagina Sistema avvisa se in produzione ci sono account @example.com', function () {
    $admin = installManuTrack(['email' => 'admin@cliente.test']);
    User::factory()->create(['email' => 'tecnico1@example.com']);
    $page = fn () => $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('system'));

    // In sviluppo nessun avviso
    $page()->assertOk()->assertDontSee('tecnico1@example.com');

    $this->app['env'] = 'production';
    $page()->assertOk()->assertSee(trans_choice('system.example_accounts.title', 1, ['count' => 1]))->assertSee('tecnico1@example.com');

    // Installazione dimostrativa: gli account d'esempio sono voluti
    config(['manutrack.demo' => true]);
    $page()->assertOk()->assertDontSee('tecnico1@example.com');
});
