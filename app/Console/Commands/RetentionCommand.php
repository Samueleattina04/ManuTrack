<?php

namespace App\Console\Commands;

use App\Support\Gdpr\Retention;
use Illuminate\Console\Command;

/**
 * Conservazione dei dati: anonimizza le persone disattivate da troppo tempo, cancella il
 * registro attività più vecchio del limite e gli ZIP delle esportazioni scaduti.
 */
class RetentionCommand extends Command
{
    protected $signature = 'manutrack:conservazione';

    protected $description = 'Applica le regole di conservazione dei dati (anonimizzazione, registro, esportazioni)';

    public function handle(Retention $retention): int
    {
        $result = $retention->run();
        $this->components->info(__('gdpr.retention.done', $result));

        return self::SUCCESS;
    }
}
