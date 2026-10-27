<?php

/*
 * Installazione demo separata dai clienti: pacchetto, .env e database devono
 * dire tutti e tre "demo", altrimenti nulla viene installato o azzerato.
 */

use App\Models\Part;
use App\Models\User;
use App\Support\Demo\Edition;
use App\Support\Settings;
use App\Support\Setup\InstallState;
use App\Support\Update\Updater;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** Simula il pacchetto caricato sul server (release.json dell'edizione indicata) */
function fakePackage($test, string $edition): void
{
    $dir = (string) config('manutrack.update.base_path');
    File::ensureDirectoryExists($dir);
    file_put_contents($dir.'/release.json', json_encode(['version' => '9.9.9', 'edition' => $edition, 'files' => ['VERSION' => 6]]));
}

function demoReady($test): void
{
    fakePackage($test, 'demo');
    config(['manutrack.demo' => true]);
}

it('senza release.json è sviluppo; i pacchetti vecchi senza edizione sono clienti', function () {
    expect(Edition::package())->toBe(Edition::DEV);
    $dir = (string) config('manutrack.update.base_path');
    File::ensureDirectoryExists($dir);
    file_put_contents($dir.'/release.json', json_encode(['version' => '0.7.0', 'files' => []]));
    expect(Edition::package())->toBe(Edition::CLIENT);
});

it('manutrack:demo si rifiuta con il pacchetto cliente', function () {
    fakePackage($this, 'cliente');
    config(['manutrack.demo' => true]);

    $this->artisan('manutrack:demo')->expectsOutputToContain('non è il pacchetto demo')->assertFailed();
    expect(InstallState::databaseSaysInstalled())->toBeFalse();
});

it('manutrack:demo si rifiuta senza MANUTRACK_DEMO=true', function () {
    fakePackage($this, 'demo');
    config(['manutrack.demo' => false]);

    $this->artisan('manutrack:demo')->assertFailed();
    expect(InstallState::databaseSaysInstalled())->toBeFalse();
});

it('un database cliente non viene mai azzerato, nemmeno con pacchetto demo e .env demo', function () {
    $admin = installManuTrack(['email' => 'titolare@cliente.test']);
    app(Settings::class)->set(Edition::SETTING, Edition::CLIENT);
    demoReady($this);

    $this->artisan('manutrack:demo', ['--azzera' => true])->expectsOutputToContain('non viene toccato')->assertFailed();
    expect(User::query()->sole()->email)->toBe('titolare@cliente.test')
        ->and(Edition::isDemo())->toBeFalse()
        ->and(Edition::mismatches())->toHaveCount(2);

    // La pagina Sistema lo segnala
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('system'))
        ->assertSee(__('demo.mismatch.package_db', ['package' => 'demo', 'database' => 'cliente']))
        ->assertSee(__('demo.mismatch.env_on'));
});

it('l\'installazione normale si rifiuta con il pacchetto demo', function () {
    fakePackage($this, 'demo');

    expect(fn () => installManuTrack())->toThrow(RuntimeException::class, 'pacchetto demo');
    expect(InstallState::databaseSaysInstalled())->toBeFalse();
});

it('l\'installazione cliente registra la propria edizione', function () {
    fakePackage($this, 'cliente');
    installManuTrack();
    expect(Edition::database())->toBe(Edition::CLIENT);
});

it('installa la demo su un database vuoto e la azzera solo con --azzera', function () {
    demoReady($this);

    $this->artisan('manutrack:demo')->assertSuccessful();
    expect(Edition::database())->toBe(Edition::DEMO)->and(Edition::isDemo())->toBeTrue()
        ->and(User::query()->where('email', 'admin@example.com')->exists())->toBeTrue()
        ->and(Part::query()->count())->toBeGreaterThan(0);

    // Un visitore aggiunge una persona e carica un file
    User::factory()->create(['email' => 'visitatore@example.com']);
    File::ensureDirectoryExists(storage_path('app/private/attachments'));
    file_put_contents(storage_path('app/private/attachments/foto.webp'), 'x');

    $this->artisan('manutrack:demo')->expectsOutputToContain('--azzera')->assertFailed();
    $this->artisan('manutrack:demo', ['--azzera' => true])->assertSuccessful();

    expect(User::query()->where('email', 'visitatore@example.com')->exists())->toBeFalse()
        ->and(file_exists(storage_path('app/private/attachments/foto.webp')))->toBeFalse()
        ->and(app(Settings::class)->get('demo.reset_at'))->not->toBeNull();
});

it('il banner demo compare solo in un\'installazione demo', function () {
    demoReady($this);
    Artisan::call('manutrack:demo');
    $admin = User::query()->where('email', 'admin@example.com')->sole();

    $this->get('/login')->assertSee(__('demo.login_hint', ['email' => 'admin@example.com', 'password' => 'manutrack-demo']));
    $this->actingAs($admin)->get('/')->assertSee(__('demo.banner', ['time' => '03:00']));

    config(['manutrack.demo' => false]);
    $this->actingAs($admin)->get('/')->assertDontSee(__('demo.banner', ['time' => '03:00']));
});

it('l\'aggiornamento si rifiuta se pacchetto e database sono di edizioni diverse', function () {
    installManuTrack();
    app(Settings::class)->set(Edition::SETTING, Edition::CLIENT);
    fakePackage($this, 'demo');

    expect(Updater::editionBlocker())->toContain('"demo"')->toContain('"cliente"');
    $result = app(Updater::class)->run('manual', force: true);
    expect($result->status)->toBe('skipped')->and(app(Updater::class)->autoBlocker())->toContain('"demo"');

    fakePackage($this, 'cliente');
    expect(Updater::editionBlocker())->toBeNull();
});

it('l\'azzeramento notturno è pianificato e non tocca un cliente', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'manutrack:demo-reset');
    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 3 * * *');

    installManuTrack(['email' => 'titolare@cliente.test']);
    demoReady($this); // pacchetto e .env "demo" per errore, database cliente
    $event->run(app());
    expect(User::query()->sole()->email)->toBe('titolare@cliente.test');
});

it('se il database non si riesce a leggere non si azzera nulla', function () {
    installManuTrack(['email' => 'titolare@cliente.test']);
    demoReady($this);
    $connection = config('database.default');
    $password = config("database.connections.$connection.password");

    config(["database.connections.$connection.password" => 'password-sbagliata']);
    DB::purge($connection);
    expect(Edition::databaseOrError())->toBe(Edition::UNREADABLE)
        ->and(Edition::demoBlocker())->toBe(__('demo.blocked.unreadable'));
    $this->artisan('manutrack:demo', ['--azzera' => true])->assertFailed();

    config(["database.connections.$connection.password" => $password]);
    DB::purge($connection);
    expect(User::query()->sole()->email)->toBe('titolare@cliente.test');
});
