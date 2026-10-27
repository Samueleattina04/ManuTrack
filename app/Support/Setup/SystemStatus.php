<?php

namespace App\Support\Setup;

use App\Models\SystemVersion;
use App\Models\User;
use App\Support\AppVersion;
use App\Support\Backup\DatabaseBackup;
use App\Support\Files\PhotoProcessor;
use App\Support\Push\PushDispatcher;
use App\Support\Update\Updater;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Raccoglie lo stato dell'installazione per la pagina Sistema e la panoramica.
 */
class SystemStatus
{
    public const CRON_KEY = 'manutrack.cron.last_run';

    public function __construct(
        private Requirements $requirements,
        private Updater $updater,
        private DatabaseBackup $backups,
    ) {}

    /**
     * Ultima esecuzione registrata dal cron, se presente.
     */
    public function cronLastRun(): ?Carbon
    {
        try {
            $value = Cache::get(self::CRON_KEY);

            return $value ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function cronHealthy(): bool
    {
        $last = $this->cronLastRun();

        return $last !== null && $last->gt(now()->subMinutes(5));
    }

    public function pendingJobs(): ?int
    {
        try {
            return Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function failedJobs(): ?int
    {
        try {
            return Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Domini riservati agli esempi: account così in produzione sono quasi certamente di prova */
    public const EXAMPLE_DOMAINS = ['example.com', 'example.org', 'example.net'];

    /**
     * Account con indirizzi d'esempio in un ambiente di produzione (non dimostrativo).
     *
     * @return list<string>
     */
    public function exampleAccounts(): array
    {
        if (! app()->isProduction() || config('manutrack.demo')) {
            return [];
        }

        return User::query()
            ->where(function ($q): void {
                foreach (self::EXAMPLE_DOMAINS as $domain) {
                    $q->orWhere('email', 'like', '%@'.$domain);
                }
            })
            ->orderBy('email')->pluck('email')->all();
    }

    public function pushConfigured(): bool
    {
        return filled(config('manutrack.webpush.public_key')) && filled(config('manutrack.webpush.private_key'));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'code_version' => AppVersion::code(),
            'installed_version' => AppVersion::installed(),
            'update_pending' => AppVersion::updatePending(),
            'auto_blocker' => AppVersion::updatePending() ? $this->updater->autoBlocker() : null,
            'update_error' => settings(Updater::ERROR_SETTING),
            'cron_last_run' => $this->cronLastRun(),
            'cron_healthy' => $this->cronHealthy(),
            'queue_driver' => config('queue.default'),
            'pending_jobs' => $this->pendingJobs(),
            'failed_jobs' => $this->failedJobs(),
            'push_configured' => $this->pushConfigured(),
            'example_accounts' => $this->exampleAccounts(),
            'push_early_response' => PushDispatcher::earlyResponseMode(),
            'image_driver' => Requirements::imageDriver(),
            'photo_format' => PhotoProcessor::outputFormat(),
            'php' => PHP_VERSION,
            'database' => $this->requirements->databaseCheck()['detail'],
        ];
    }

    /**
     * @return Collection<int, SystemVersion>
     */
    public function history(int $limit = 10)
    {
        try {
            return SystemVersion::query()->latest('id')->limit($limit)->get();
        } catch (Throwable) {
            return collect();
        }
    }

    /**
     * @return list<array{file: string, name: string, size: int, created: int}>
     */
    public function backups(): array
    {
        try {
            return $this->backups->list();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    public function checks(): array
    {
        return $this->requirements->checks();
    }
}
