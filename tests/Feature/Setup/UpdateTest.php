<?php

use App\Models\SystemVersion;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\AppVersion;
use App\Support\FileLock;
use App\Support\Setup\SystemSync;
use App\Support\Update\Updater;
use App\Support\Update\UpdateResult;
use Illuminate\Support\Facades\File;

/**
 * Crea nella cartella di prova un pacchetto "caricato" con release.json.
 *
 * @param  array<string, string>  $files  percorso => contenuto
 */
function fakeRelease(string $dir, string $version, array $files = ['app/Uno.php' => '<?php // uno', 'vendor/autoload.php' => '<?php'], ?int $mtime = null): void
{
    $manifest = [];
    foreach ($files as $path => $content) {
        File::ensureDirectoryExists(dirname($dir.'/'.$path));
        file_put_contents($dir.'/'.$path, $content);
        $manifest[$path] = strlen($content);
        touch($dir.'/'.$path, $mtime ?? time() - 3600);
    }
    file_put_contents($dir.'/release.json', json_encode(['version' => $version, 'built_at' => now()->toIso8601String(), 'files' => $manifest]));
    touch($dir.'/release.json', $mtime ?? time() - 3600);
}

beforeEach(function () {
    installManuTrack();
});

it('non fa nulla se la versione è già applicata', function () {
    $result = app(Updater::class)->run();

    expect($result->status)->toBe(UpdateResult::UP_TO_DATE)
        ->and(SystemVersion::query()->count())->toBe(1);
});

it('aggiorna creando prima una copia del database', function () {
    AppVersion::fake('9.1.0');

    $this->artisan('manutrack:update')->assertSuccessful();

    $record = SystemVersion::query()->latest('id')->first();
    expect($record->status)->toBe(SystemVersion::SUCCESS)
        ->and($record->previous_version)->toBe(trim(file_get_contents(base_path('VERSION'))))
        ->and($record->backup_file)->toStartWith('manutrack-')
        ->and(is_file(config('manutrack.backups.path').'/'.$record->backup_file))->toBeTrue()
        ->and(AppVersion::installed())->toBe('9.1.0')
        ->and(app()->isDownForMaintenance())->toBeFalse()
        // Avviso a chi gestisce il sistema
        ->and(UserNotification::query()->where('event', 'aggiornamento')->sole()->title)->toBe(__('notifications.update.ok', ['version' => '9.1.0']));

    // Ripetibile: la seconda volta non cambia nulla
    $this->artisan('manutrack:update')->assertSuccessful();
    expect(SystemVersion::query()->where('version', '9.1.0')->count())->toBe(1);
});

it('se fallisce lascia l\'app utilizzabile e registra l\'errore', function () {
    AppVersion::fake('9.2.0');
    $this->mock(SystemSync::class)->shouldReceive('run')->andThrow(new RuntimeException('migrazione di prova rotta'));

    $this->artisan('manutrack:update')->assertFailed();

    $record = SystemVersion::query()->latest('id')->first();
    expect($record->status)->toBe(SystemVersion::FAILED)
        ->and($record->error)->toContain('migrazione di prova rotta')
        ->and(app()->isDownForMaintenance())->toBeFalse()
        ->and(AppVersion::installed())->not->toBe('9.2.0')
        ->and(settings(Updater::ERROR_SETTING)['message'])->toContain('migrazione di prova rotta')
        ->and(UserNotification::query()->where('event', 'aggiornamento')->sole()->body)->toContain('migrazione di prova rotta');

    // L'avviso compare nelle pagine, ma solo a chi gestisce il sistema
    $admin = User::query()->first();
    $this->actingAs($admin)->get('/')->assertOk()->assertSee(__('system.banner.failed_title', ['version' => '9.2.0']));
});

it('non parte se un altro aggiornamento è in corso', function () {
    AppVersion::fake('9.3.0');
    $lock = new FileLock('manutrack-update');
    expect($lock->acquire())->toBeTrue();

    $result = app(Updater::class)->run();
    $lock->release();

    expect($result->status)->toBe(UpdateResult::LOCKED)
        ->and(AppVersion::installed())->not->toBe('9.3.0');
});

describe('aggiornamento automatico dal cron', function () {
    it('non aggiorna senza release.json', function () {
        AppVersion::fake('9.4.0');

        $result = app(Updater::class)->runAutomatic();

        expect($result->status)->toBe(UpdateResult::SKIPPED)
            ->and($result->message)->toBe(__('system.update.no_marker'))
            ->and(AppVersion::installed())->not->toBe('9.4.0');
    });

    it('non aggiorna se release.json è di un\'altra versione', function () {
        AppVersion::fake('9.4.0');
        fakeRelease($this->sandbox.'/release', '9.3.9');

        expect(app(Updater::class)->runAutomatic()->status)->toBe(UpdateResult::SKIPPED)
            ->and(AppVersion::installed())->not->toBe('9.4.0');
    });

    it('non aggiorna se mancano file o hanno dimensione diversa', function () {
        AppVersion::fake('9.4.0');
        $dir = $this->sandbox.'/release';
        fakeRelease($dir, '9.4.0');
        file_put_contents($dir.'/app/Uno.php', '<?php // caricato a metà');
        touch($dir.'/app/Uno.php', time() - 3600);

        $result = app(Updater::class)->runAutomatic();
        expect($result->status)->toBe(UpdateResult::SKIPPED)
            ->and($result->message)->toContain('app/Uno.php');

        unlink($dir.'/app/Uno.php');
        expect(app(Updater::class)->runAutomatic()->message)->toContain('manca app/Uno.php');
    });

    it('attende qualche minuto dopo l\'ultima modifica dei file', function () {
        AppVersion::fake('9.4.0');
        config(['manutrack.update.settle_minutes' => 5]);
        fakeRelease($this->sandbox.'/release', '9.4.0', mtime: time() - 60);

        $result = app(Updater::class)->runAutomatic();
        expect($result->status)->toBe(UpdateResult::SKIPPED)
            ->and($result->message)->toContain('attendo');
    });

    it('aggiorna quando il pacchetto è completo e stabile', function () {
        AppVersion::fake('9.4.0');
        fakeRelease($this->sandbox.'/release', '9.4.0');

        expect(app(Updater::class)->runAutomatic()->status)->toBe(UpdateResult::SUCCESS)
            ->and(AppVersion::installed())->toBe('9.4.0')
            ->and(SystemVersion::query()->latest('id')->first()->trigger)->toBe('auto');
    });

    it('dopo un errore non ritenta finché non lo si chiede', function () {
        AppVersion::fake('9.5.0');
        fakeRelease($this->sandbox.'/release', '9.5.0');
        $this->mock(SystemSync::class)->shouldReceive('run')->once()->andThrow(new RuntimeException('rotto'));

        expect(app(Updater::class)->runAutomatic()->status)->toBe(UpdateResult::FAILED);
        expect(app(Updater::class)->runAutomatic()->message)->toBe(__('system.update.failed_before', ['version' => '9.5.0']));

        $this->artisan('manutrack:update --retry')->assertSuccessful();
        $this->mock(SystemSync::class)->shouldReceive('run')->once()->andReturn([]);

        expect(app(Updater::class)->runAutomatic()->status)->toBe(UpdateResult::SUCCESS)
            ->and(AppVersion::installed())->toBe('9.5.0')
            ->and(settings(Updater::ERROR_SETTING))->toBeNull();
    });

    it('si può disattivare', function () {
        AppVersion::fake('9.6.0');
        config(['manutrack.update.auto' => false]);
        fakeRelease($this->sandbox.'/release', '9.6.0');

        expect(app(Updater::class)->runAutomatic()->status)->toBe(UpdateResult::SKIPPED)
            ->and(AppVersion::installed())->not->toBe('9.6.0');
    });
});
