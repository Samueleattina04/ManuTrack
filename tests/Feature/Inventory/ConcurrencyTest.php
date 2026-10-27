<?php

/*
 * Concorrenza reale: più processi PHP, ognuno con la sua connessione, muovono
 * lo stesso ricambio nello stesso istante. Senza il blocco delle righe
 * (SELECT … FOR UPDATE) la giacenza andrebbe sotto zero o i resi
 * supererebbero il prelevato. Gira su MariaDB e su MySQL 8 (matrice CI).
 */

use App\Enums\WorkOrderStatus;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Support\Inventory\Stock;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->main = makeWarehouse();
    $this->part = makePart();
});

it('otto prelievi contemporanei su tre pezzi: ne passano tre, la giacenza non va sotto zero', function () {
    app(Stock::class)->receive($this->part, $this->main, 3, $this->admin);

    $results = raceWorkers('stock-worker.php', 8, ['issue', $this->part->id, $this->main->id, $this->admin->id, 1, 0]);

    expect(array_count_values($results))->toEqualCanonicalizing(['ok' => 3, 'rifiutato' => 5]);
    expect((float) StockLevel::query()->sole()->quantity)->toBe(0.0)
        ->and((float) $this->part->fresh()->stock_total)->toBe(0.0)
        ->and(StockMovement::query()->where('type', 'prelievo')->count())->toBe(3)
        // Le giacenze risultanti sono 2, 1, 0: nessun movimento ha letto un valore vecchio
        ->and(StockMovement::query()->where('type', 'prelievo')->orderBy('id')->pluck('balance_after')->map(fn ($v) => (float) $v)->all())->toBe([2.0, 1.0, 0.0]);
})->skip(fn () => ! function_exists('proc_open'), 'proc_open non disponibile');

it('sei resi contemporanei non superano quanto prelevato per l\'ordine', function () {
    $order = makeOrder($this->admin, [$this->admin->id]);
    $order->forceFill(['status' => WorkOrderStatus::InProgress])->save();
    $stock = app(Stock::class);
    $stock->receive($this->part, $this->main, 10, $this->admin, 4);
    $stock->issue($this->part, $this->main, 2, $this->admin, $order);

    $results = raceWorkers('stock-worker.php', 6, ['return', $this->part->id, $this->main->id, $this->admin->id, 1, $order->id]);

    expect(array_count_values($results))->toEqualCanonicalizing(['ok' => 2, 'rifiutato' => 4])
        ->and($stock->netIssued($order, $this->part))->toBe(0.0)
        ->and((float) $this->part->fresh()->stock_total)->toBe(10.0);
})->skip(fn () => ! function_exists('proc_open'), 'proc_open non disponibile');
