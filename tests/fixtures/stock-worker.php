<?php

/**
 * Processo separato per i test di concorrenza del magazzino: avvia ManuTrack
 * con la propria connessione al database, aspetta l'istante comune di partenza
 * e prova un movimento. Stampa "ok" oppure "rifiutato".
 *
 *   php stock-worker.php <issue|return> <part> <warehouse> <user> <qty> <order|0> <partenza>
 */

use App\Models\Part;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Support\Inventory\Stock;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $op, $partId, $warehouseId, $userId, $qty, $orderId, $startAt] = $argv;
$part = Part::query()->findOrFail((int) $partId);
$warehouse = Warehouse::query()->findOrFail((int) $warehouseId);
$user = User::query()->findOrFail((int) $userId);
$order = (int) $orderId > 0 ? WorkOrder::query()->findOrFail((int) $orderId) : null;
$stock = app(Stock::class);

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    $op === 'return'
        ? $stock->giveBack($part, $warehouse, $qty, $user, $order)
        : $stock->issue($part, $warehouse, $qty, $user, $order);
    echo 'ok';
} catch (ValidationException) {
    echo 'rifiutato';
}
