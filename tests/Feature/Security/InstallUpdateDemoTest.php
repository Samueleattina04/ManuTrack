<?php

use App\Http\Controllers\DesignController;
use App\Providers\AppServiceProvider;
use App\Support\Access\Access;
use App\Support\Demo\DemoInstaller;
use App\Support\FileLock;
use App\Support\Gdpr\Eraser;
use App\Support\Navigation;
use App\Support\Setup\InstallState;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
 * Controllo di sicurezza 0.4: installazione, aggiornamento e demo non raggiungibili né
 * riattivabili in produzione; nessuna rotta di sviluppo con APP_ENV=production.
 */

afterEach(function () {
    $this->app['env'] = 'testing';
    DB::prohibitDestructiveCommands(false);
    @unlink(Eraser::reportPath());
});

it('l\'installazione dal browser non parte senza codice in .env, nemmeno mandando un codice vuoto', function () {
    config(['manutrack.install_token' => '']);
    $this->post('/install', ['token' => '', 'company' => 'Prova', 'name' => 'Prova', 'email' => 'prova@example.test', 'password' => 'Password1234', 'password_confirmation' => 'Password1234', 'palette' => 'rame'])
        ->assertSessionHasErrors('token');
    expect(InstallState::isInstalled())->toBeFalse();
});

it('due installazioni contemporanee: la seconda trova il blocco e non installa nulla', function () {
    config(['manutrack.install_token' => 'codice-lungo-di-prova']);
    $other = new FileLock('manutrack-install');
    expect($other->acquire())->toBeTrue();
    try {
        $this->post('/install', ['token' => 'codice-lungo-di-prova', 'company' => 'Prova', 'name' => 'Prova', 'email' => 'prova@example.test', 'password' => 'Password1234', 'password_confirmation' => 'Password1234', 'palette' => 'rame'])
            ->assertSessionHasErrors('install');
    } finally {
        $other->release();
    }
    expect(Schema::hasTable('users') && DB::table('users')->exists())->toBeFalse();
});

it('dopo la cancellazione totale l\'installazione non si riattiva, né dal browser né dal terminale', function () {
    $admin = installManuTrack();
    config(['manutrack.install_token' => 'codice-lungo-di-prova']);
    app(Eraser::class)->run($admin, 'prova');
    expect(Schema::hasTable('users'))->toBeFalse();

    $this->get('/install')->assertStatus(410);
    $this->post('/install', ['token' => 'codice-lungo-di-prova'])->assertStatus(410);
    // Anche cancellando il file di blocco, il rapporto di cancellazione tiene tutto fermo
    @unlink(InstallState::lockPath());
    $this->get('/install')->assertStatus(410);

    Artisan::call('manutrack:install', ['--company' => 'Altra', '--name' => 'Altra', '--email' => 'altra@example.test', '--password' => 'Password1234', '--no-interaction' => true]);
    expect(Schema::hasTable('users'))->toBeFalse();
});

it('l\'aggiornamento dalla pagina Sistema vuole l\'amministratore con la password riconfermata', function () {
    $admin = installManuTrack();
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => 0])->post(route('system.update'))->assertRedirect(route('password.confirm'));
    foreach ([Access::MANAGER, Access::TECHNICIAN, Access::VIEWER] as $role) {
        $this->actingAs(userWithRole($role))->withSession(['auth.password_confirmed_at' => time()])->post(route('system.update'))->assertForbidden();
    }
});

it('in produzione /design non esiste e non compare nel menu', function () {
    $admin = installManuTrack();
    $this->actingAs($admin)->get('/design')->assertOk();

    $this->app['env'] = 'production';
    $this->get('/design')->assertNotFound();
    expect(collect(Navigation::admin())->pluck('key'))->not->toContain('design');
});

it('in produzione nessuna rotta di sviluppo o di debug', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->uri());
    foreach (['_ignition', 'telescope', '_debugbar', 'horizon', 'pulse', 'clockwork', 'log-viewer'] as $dev) {
        expect($routes->contains(fn ($uri) => str_starts_with($uri, $dev)))->toBeFalse($dev);
    }
    // L'unica pagina per chi sviluppa è /design, chiusa in produzione (test sopra)
    expect((new ReflectionMethod(DesignController::class, '__invoke'))->getFileName())->toBeFile();
});

it('con APP_DEBUG=false gli errori non mostrano dettagli', function () {
    installManuTrack();
    config(['app.debug' => false]);
    Route::get('/prova-errore', fn () => throw new RuntimeException('Dettaglio segreto: password del database'))->middleware('web');

    $this->get('/prova-errore')->assertStatus(500)->assertDontSee('Dettaglio segreto')->assertDontSee('RuntimeException')->assertDontSee(base_path());
});

it('in produzione migrate:fresh e db:wipe non partono, nemmeno con --force; la demo confermata sì', function () {
    installManuTrack();
    $this->app['env'] = 'production';
    (new AppServiceProvider($this->app))->boot();

    expect(Artisan::call('migrate:fresh', ['--force' => true]))->not->toBe(0)
        ->and(Artisan::call('db:wipe', ['--force' => true]))->not->toBe(0)
        ->and(Artisan::call('migrate:reset', ['--force' => true]))->not->toBe(0)
        ->and(Schema::hasTable('users'))->toBeTrue()
        ->and(DB::table('users')->count())->toBe(1);

    // Il seeder in produzione non crea account dimostrativi
    Artisan::call('db:seed', ['--force' => true]);
    expect(DB::table('users')->where('email', 'admin@example.com')->exists())->toBeFalse();

    // L'azzeramento della demo resta bloccato su un cliente anche con il divieto tolto a mano
    FreshCommand::prohibit(false);
    WipeCommand::prohibit(false);
    expect(fn () => app(DemoInstaller::class)->run())->toThrow(RuntimeException::class);
    expect(DB::table('users')->count())->toBe(1);
});
