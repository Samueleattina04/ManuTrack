<?php

namespace App\Console\Commands;

use App\Support\AppVersion;
use App\Support\Update\Updater;
use App\Support\Update\UpdateResult;
use Illuminate\Console\Command;

class UpdateCommand extends Command
{
    protected $signature = 'manutrack:update
        {--auto : Modalità cron: aggiorna solo se il pacchetto caricato è completo e stabile}
        {--retry : Permette al cron di ritentare una versione che era fallita}
        {--force : Riesegue migrazioni e allineamenti anche se la versione è già applicata}';

    protected $description = 'Applica un aggiornamento: copia del database, migrazioni, allineamento dati e cache';

    public function handle(Updater $updater): int
    {
        if ($this->option('retry')) {
            $updater->allowRetry();
            $this->components->info(__('system.update.retry_armed', ['version' => AppVersion::code()]));

            return self::SUCCESS;
        }

        $updater->onOutput(fn (string $line) => $this->components->task($line));

        $result = $this->option('auto')
            ? $updater->runAutomatic()
            : $updater->run('manual', (bool) $this->option('force'));

        return $this->report($result);
    }

    private function report(UpdateResult $result): int
    {
        switch ($result->status) {
            case UpdateResult::SUCCESS:
                $this->components->info(__('system.update.done', ['from' => $result->from ?? '—', 'to' => $result->to]));
                $this->line('  '.__('system.update.backup_saved', ['file' => $result->backup]));

                return self::SUCCESS;

            case UpdateResult::UP_TO_DATE:
                $this->components->info(__('system.update.up_to_date', ['version' => $result->to]));

                return self::SUCCESS;

            case UpdateResult::SKIPPED:
                $this->components->info((string) $result->message);

                return self::SUCCESS;

            case UpdateResult::FAILED:
                $this->components->error(__('system.update.failed', ['version' => $result->to, 'message' => $result->message]));
                if ($result->backup) {
                    $this->line('  '.__('system.update.restore_hint', ['file' => $result->backup]));
                }

                return self::FAILURE;

            default:
                $this->components->warn((string) $result->message);

                return self::FAILURE;
        }
    }
}
