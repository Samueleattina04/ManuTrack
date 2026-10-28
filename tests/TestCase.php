<?php

namespace Tests;

use App\Support\AppVersion;
use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Ogni test lavora in una cartella temporanea: storage, file .env e copie del
 * database non toccano mai i file veri del progetto. Il database di prova
 * (manutrack_test) parte vuoto.
 */
abstract class TestCase extends BaseTestCase
{
    protected string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'manutrack-test-'.getmypid().'-'.bin2hex(random_bytes(4));
        foreach (['storage/app/private', 'storage/framework/cache', 'storage/framework/views', 'storage/logs'] as $dir) {
            File::ensureDirectoryExists($this->sandbox.'/'.$dir);
        }
        file_put_contents($this->sandbox.'/.env', "APP_NAME=ManuTrack\nMANUTRACK_INSTALL_TOKEN=\n");

        $this->app->useStoragePath($this->sandbox.'/storage');
        $this->app->useEnvironmentPath($this->sandbox);
        config([
            'manutrack.backups.path' => $this->sandbox.'/storage/app/backups',
            'manutrack.update.base_path' => $this->sandbox.'/release',
            // I dischi sono configurati prima della cartella temporanea: vanno spostati anche loro
            'filesystems.disks.local.root' => $this->sandbox.'/storage/app/private',
            'filesystems.disks.public.root' => $this->sandbox.'/storage/app/public',
        ]);
        $this->app->make('filesystem')->forgetDisk(['local', 'public']);

        $this->withoutVite();
        Artisan::call('db:wipe', ['--force' => true]);
        $this->app->make(Settings::class)->flush();
    }

    protected function tearDown(): void
    {
        AppVersion::fake(null);
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    protected function envFile(): string
    {
        return (string) file_get_contents($this->sandbox.'/.env');
    }
}
