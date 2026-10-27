<?php

namespace App\Support\Demo;

use App\Support\FileLock;
use App\Support\Settings;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Installa o azzera l'installazione demo: database ricreato da zero, file
 * caricati e sessioni cancellati, dati dimostrativi di nuovo al loro posto.
 *
 * Parte solo se Edition::demoBlocker() non trova problemi: pacchetto demo,
 * MANUTRACK_DEMO=true e database vuoto o già segnato come demo. Un database
 * cliente non viene mai toccato.
 */
class DemoInstaller
{
    public function __construct(private Settings $settings) {}

    public function run(?Closure $output = null): void
    {
        $say = $output ?? fn (string $line) => null;

        if ($reason = Edition::demoBlocker()) {
            throw new RuntimeException($reason);
        }
        // I seeder sono solo nel pacchetto demo (e nel repository)
        if (! class_exists(DatabaseSeeder::class)) {
            throw new RuntimeException(__('demo.blocked.seeders'));
        }

        $lock = new FileLock('manutrack-demo-reset');
        if (! $lock->acquire()) {
            throw new RuntimeException(__('install.busy'));
        }

        // Ricreare il database può richiedere più del limite del server web
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $wentDown = false;
        try {
            if (! app()->isDownForMaintenance()) {
                Artisan::call('down', ['--retry' => 30, '--render' => 'errors::503']);
                $wentDown = true;
            }

            $say(__('demo.step.database'));
            $this->call('migrate:fresh', ['--force' => true]);
            $this->settings->flush();

            $say(__('demo.step.files'));
            $this->wipeFiles();

            $say(__('demo.step.data'));
            $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
            $this->settings->set(Edition::SETTING, Edition::DEMO);
            $this->settings->set('demo.reset_at', now()->toIso8601String());
        } finally {
            if ($wentDown) {
                Artisan::call('up');
            }
            $lock->release();
        }
    }

    /**
     * Esegue un comando e si ferma se non riesce: nessun "fatto" dopo un errore.
     *
     * @param  array<string, mixed>  $params
     */
    private function call(string $command, array $params): void
    {
        if (Artisan::call($command, $params) !== 0) {
            throw new RuntimeException(__('demo.step.failed', ['command' => $command, 'output' => trim(mb_strimwidth(Artisan::output(), 0, 500, '…'))]));
        }
    }

    /** File caricati dai visitatori e sessioni (gli id degli utenti ripartono da capo) */
    private function wipeFiles(): void
    {
        foreach ([storage_path('app/private'), storage_path('app/public'), storage_path('framework/sessions')] as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (File::allFiles($dir, true) as $file) {
                if ($file->getFilename() !== '.gitignore') {
                    @unlink($file->getPathname());
                }
            }
            foreach (File::directories($dir) as $sub) {
                File::deleteDirectory($sub);
            }
        }
        @unlink(storage_path('app/installed.lock'));
    }
}
