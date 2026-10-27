<?php

namespace App\Console\Commands;

use App\Actions\Preventive\GeneratePlanOrders;
use Illuminate\Console\Command;

/**
 * Genera subito gli ordini dei piani preventivi in scadenza (lo fa anche il
 * cron ogni 15 minuti). Con --prova mostra soltanto quali verrebbero creati.
 */
class PreventiveCommand extends Command
{
    protected $signature = 'manutrack:preventiva {--prova : Non crea nulla: elenca i piani in scadenza}';

    protected $description = 'Genera gli ordini di lavoro dei piani di manutenzione preventiva in scadenza';

    public function handle(GeneratePlanOrders $generator): int
    {
        $dryRun = (bool) $this->option('prova');
        $results = $generator->run(dryRun: $dryRun);

        if ($results === []) {
            $this->components->info(__('preventive.command.none'));

            return self::SUCCESS;
        }

        $this->table(
            [__('preventive.field.asset_id'), __('preventive.field.title'), __('preventive.field.next_due'), $dryRun ? '' : __('preventive.command.order')],
            array_map(fn ($r) => [
                $r['plan']->asset->code ?? '—',
                $r['plan']->title,
                $r['plan']->isTime() ? $r['plan']->next_due_on?->format('d/m/Y') : $r['plan']->next_meter_due.' '.($r['plan']->meter->unit ?? ''),
                $r['order']->number ?? __('preventive.command.would_create'),
            ], $results),
        );
        $this->components->info(trans_choice($dryRun ? 'preventive.command.dry' : 'preventive.command.done', count($results), ['count' => count($results)]));

        return self::SUCCESS;
    }
}
