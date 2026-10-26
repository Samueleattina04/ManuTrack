<?php

namespace App\Support\Update;

use App\Models\SystemVersion;
use App\Support\AppVersion;
use App\Support\Backup\DatabaseBackup;
use App\Support\FileLock;
use App\Support\Settings;
use App\Support\Setup\InstallState;
use App\Support\Setup\SystemSync;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Applica un aggiornamento: copia del database, migrazioni, allineamento dei dati
 * di sistema, cache. Ripetibile: sulla stessa versione non fa nulla.
 * Se qualcosa fallisce l'app esce comunque dalla manutenzione e l'errore viene
 * registrato e mostrato all'amministratore.
 */
class Updater
{
    public const ERROR_SETTING = 'system.update_error';

    public const RETRY_SETTING = 'system.update_retry';

    /** @var (Closure(string): void)|null */
    private ?Closure $output = null;

    public function __construct(
        private DatabaseBackup $backup,
        private Settings $settings,
    ) {}

    /**
     * @param  Closure(string): void  $output
     */
    public function onOutput(Closure $output): self
    {
        $this->output = $output;

        return $this;
    }

    public function run(string $trigger = 'manual', bool $force = false): UpdateResult
    {
        if (! InstallState::isInstalled()) {
            return UpdateResult::notInstalled();
        }

        $lock = new FileLock('manutrack-update');
        if (! $lock->acquire()) {
            return UpdateResult::locked();
        }

        try {
            return $this->apply($trigger, $force);
        } finally {
            $lock->release();
        }
    }

    /**
     * Motivo per cui il cron non deve aggiornare ora, oppure null se può procedere.
     */
    public function autoBlocker(): ?string
    {
        if (! config('manutrack.update.auto')) {
            return __('system.update.auto_disabled');
        }

        if (! InstallState::isInstalled()) {
            return __('system.update.not_installed');
        }

        $code = AppVersion::code();
        $installed = AppVersion::installed();

        if ($installed !== null && version_compare($code, $installed, '<=')) {
            return __('system.update.up_to_date', ['version' => $installed]);
        }

        $manifest = ReleaseManifest::load(config('manutrack.update.base_path') ?: base_path());
        if ($manifest === null) {
            return __('system.update.no_marker');
        }

        if ($manifest->version() !== $code) {
            return __('system.update.marker_mismatch', ['marker' => (string) $manifest->version(), 'code' => $code]);
        }

        $check = $manifest->verify();
        if ($check['problems'] !== []) {
            return __('system.update.incomplete', ['first' => $check['problems'][0], 'count' => count($check['problems'])]);
        }

        $settle = max(0, (int) config('manutrack.update.settle_minutes', 5)) * 60;
        $quietFor = time() - $check['newest_mtime'];
        if ($quietFor < $settle) {
            return __('system.update.settling', ['minutes' => (int) ceil(($settle - $quietFor) / 60)]);
        }

        $failed = SystemVersion::query()
            ->where('version', $code)
            ->where('status', SystemVersion::FAILED)
            ->exists();

        if ($failed && $this->settings->get(self::RETRY_SETTING) !== $code) {
            return __('system.update.failed_before', ['version' => $code]);
        }

        return null;
    }

    public function runAutomatic(): UpdateResult
    {
        $reason = $this->autoBlocker();
        if ($reason !== null) {
            return UpdateResult::skipped($reason);
        }

        return $this->run('auto');
    }

    /**
     * Chiede al cron di ritentare la versione corrente dopo un errore.
     */
    public function allowRetry(): void
    {
        $this->settings->set(self::RETRY_SETTING, AppVersion::code());
    }

    private function apply(string $trigger, bool $force): UpdateResult
    {
        $from = AppVersion::installed();
        $to = AppVersion::code();

        if (! $force && $from !== null && version_compare($to, $from, '<=')) {
            return UpdateResult::upToDate($from);
        }

        $record = SystemVersion::query()->create([
            'version' => $to,
            'previous_version' => $from,
            'status' => SystemVersion::RUNNING,
            'trigger' => $trigger,
            'started_at' => now(),
        ]);

        $wentDown = false;

        try {
            $this->say(__('system.update.step_backup'));
            $file = $this->backup->create('prima-di-'.$to);
            $record->update(['backup_file' => basename($file)]);
            $this->backup->prune();

            if (! app()->isDownForMaintenance()) {
                $this->say(__('system.update.step_down'));
                Artisan::call('down', ['--retry' => 60, '--render' => 'errors::503']);
                $wentDown = true;
            }

            $this->say(__('system.update.step_migrate'));
            Artisan::call('migrate', ['--force' => true]);

            $this->say(__('system.update.step_sync'));
            app(SystemSync::class)->run();

            $this->say(__('system.update.step_cache'));
            $this->refreshCaches();

            $record->update(['status' => SystemVersion::SUCCESS, 'finished_at' => now()]);
            $this->settings->forget(self::ERROR_SETTING);
            $this->settings->forget(self::RETRY_SETTING);

            return UpdateResult::success($from, $to, basename($file));
        } catch (Throwable $e) {
            report($e);

            $message = mb_strimwidth($e->getMessage(), 0, 2000, '…');
            $record->update([
                'status' => SystemVersion::FAILED,
                'error' => $message,
                'finished_at' => now(),
            ]);

            try {
                $this->settings->forget(self::RETRY_SETTING);
                $this->settings->set(self::ERROR_SETTING, [
                    'version' => $to,
                    'message' => $message,
                    'at' => now()->toIso8601String(),
                    'backup' => $record->backup_file,
                ]);
            } catch (Throwable) {
                // anche il database può essere il problema: resta il registro su file
            }

            return UpdateResult::failed($from, $to, $message, $record->backup_file);
        } finally {
            if ($wentDown) {
                Artisan::call('up');
            }
        }
    }

    private function refreshCaches(): void
    {
        foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear', 'clear-compiled'] as $command) {
            Artisan::call($command);
        }

        $this->settings->flush();

        if (app()->runningUnitTests()) {
            return;
        }

        if (config('manutrack.update.cache_config')) {
            Artisan::call('config:cache');
        }
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        Artisan::call('event:cache');
        Artisan::call('queue:restart');
    }

    private function say(string $line): void
    {
        if ($this->output) {
            ($this->output)($line);
        }
    }
}
