<?php

namespace App\Console\Commands;

use App\Support\Backup\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Throwable;

class BackupCommand extends Command
{
    protected $signature = 'manutrack:backup
        {--label=manuale : Etichetta aggiunta al nome del file}
        {--list : Mostra le copie presenti senza crearne una nuova}';

    protected $description = 'Crea una copia del database in storage/app/backups (in PHP, senza mysqldump)';

    public function handle(DatabaseBackup $backup): int
    {
        if (! $this->option('list')) {
            try {
                $file = $backup->create((string) $this->option('label'));
                $removed = $backup->prune();
            } catch (Throwable $e) {
                $this->components->error(__('system.backup.failed', ['message' => $e->getMessage()]));

                return self::FAILURE;
            }

            $this->components->info(__('system.backup.created', ['file' => basename($file), 'size' => Number::fileSize((int) filesize($file))]));
            if ($removed > 0) {
                $this->line('  '.__('system.backup.pruned', ['count' => $removed, 'keep' => config('manutrack.backups.keep')]));
            }
        }

        $rows = array_map(fn ($b) => [$b['name'], Number::fileSize($b['size']), date('d/m/Y H:i', $b['created'])], $backup->list());
        $this->table([__('system.backup.file'), __('system.backup.size'), __('system.backup.date')], $rows);

        return self::SUCCESS;
    }
}
