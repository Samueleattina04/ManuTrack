<?php

namespace App\Console\Commands;

use App\Support\Gdpr\Eraser;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Cancellazione totale dell'installazione dal terminale (fine contratto).
 * Uso: php artisan manutrack:cancella-tutto --conferma="CANCELLA NOME AZIENDA"
 */
class EraseCommand extends Command
{
    protected $signature = 'manutrack:cancella-tutto {--conferma= : la frase "CANCELLA <NOME AZIENDA>" in maiuscolo}';

    protected $description = 'Cancella tutti i dati dell\'installazione (fine contratto) e lascia un rapporto';

    public function handle(Eraser $eraser): int
    {
        if (Eraser::erased()) {
            $this->components->warn(__('gdpr.erase.already'));

            return self::SUCCESS;
        }
        try {
            $eraser->authorize(null, (string) $this->option('conferma'), null, fromConsole: true);
        } catch (ValidationException $e) {
            $this->components->error($e->validator->errors()->first());

            return self::FAILURE;
        }
        $report = $eraser->run(null, 'terminale');
        $this->components->info(__('gdpr.erase.done_console', ['rows' => $report['righe_cancellate'], 'path' => Eraser::reportPath()]));

        return self::SUCCESS;
    }
}
