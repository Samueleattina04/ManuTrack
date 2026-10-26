<?php

namespace App\Console\Commands;

use App\Models\SystemVersion;
use App\Support\Backup\DatabaseBackup;
use App\Support\FileLock;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Number;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class RestoreCommand extends Command
{
    protected $signature = 'manutrack:restore
        {file? : Nome del file in storage/app/backups (predefinito: si sceglie da un elenco)}
        {--force : Non chiede conferma}
        {--no-safety : Non crea una copia dello stato attuale prima di ripristinare}';

    protected $description = 'Ripristina il database da una copia creata da ManuTrack';

    public function handle(DatabaseBackup $backup, Settings $settings): int
    {
        $list = $backup->list();
        if ($list === []) {
            $this->components->error(__('system.restore.none'));

            return self::FAILURE;
        }

        $name = $this->argument('file');
        if (! $name) {
            if (! $this->input->isInteractive()) {
                $this->components->error(__('system.restore.need_file'));

                return self::INVALID;
            }
            $name = select(
                __('system.restore.choose'),
                collect($list)->mapWithKeys(fn ($b) => [$b['name'] => $b['name'].' · '.Number::fileSize($b['size'])])->all(),
            );
        }

        $match = collect($list)->firstWhere('name', basename((string) $name));
        if (! $match) {
            $this->components->error(__('system.restore.not_found', ['file' => $name]));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! confirm(__('system.restore.confirm', ['file' => $match['name']]), default: false)) {
            $this->components->info(__('system.restore.cancelled'));

            return self::SUCCESS;
        }

        // Stesso blocco degli aggiornamenti: mai un ripristino durante un aggiornamento
        $lock = new FileLock('manutrack-update');
        if (! $lock->acquire()) {
            $this->components->error(__('system.update.locked'));

            return self::FAILURE;
        }

        $wentDown = false;
        try {
            if (! $this->option('no-safety')) {
                $safety = $backup->create('prima-del-ripristino');
                $this->components->task(__('system.restore.safety', ['file' => basename($safety)]));
            }

            if (! app()->isDownForMaintenance()) {
                Artisan::call('down', ['--retry' => 60, '--render' => 'errors::503']);
                $wentDown = true;
            }

            $this->components->task(__('system.restore.running'), fn () => $backup->restore($match['file']));

            // Un aggiornamento rimasto "in corso" nella copia viene segnato come fallito,
            // così il cron non lo ritenta da solo finché non lo si chiede con --retry.
            SystemVersion::query()->where('status', SystemVersion::RUNNING)->update([
                'status' => SystemVersion::FAILED,
                'error' => __('system.restore.interrupted', ['file' => $match['name']]),
                'finished_at' => now(),
            ]);
            $settings->flush();
            Artisan::call('cache:clear');
        } catch (Throwable $e) {
            $this->components->error(__('system.restore.failed', ['message' => $e->getMessage()]));

            return self::FAILURE;
        } finally {
            if ($wentDown) {
                Artisan::call('up');
            }
            $lock->release();
        }

        $this->components->info(__('system.restore.done', ['file' => $match['name']]));

        return self::SUCCESS;
    }
}
