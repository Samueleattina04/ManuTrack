<?php

/**
 * Processo separato per il test di concorrenza della preventiva: aspetta
 * l'istante comune e prova a generare l'ordine del piano, come farebbero
 * due esecuzioni del cron sovrapposte. Stampa il numero dell'ordine o "nessuno".
 *
 *   php plan-worker.php <piano> <partenza>
 */

use App\Actions\Preventive\GeneratePlanOrders;
use App\Models\MaintenancePlan;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $planId, $startAt] = $argv;
$plan = MaintenancePlan::query()->findOrFail((int) $planId);

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

$order = app(GeneratePlanOrders::class)->generate($plan, null);
echo $order ? 'creato' : 'nessuno';
