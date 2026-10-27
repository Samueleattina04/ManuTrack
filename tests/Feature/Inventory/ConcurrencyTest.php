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

/**
 * @return list<string> esito di ogni processo
 */
function raceWorkers(int $count, array $args): array
{
    $db = config('database.connections.'.config('database.default'));
    $env = array_merge(getenv(), [
        'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'APP_DEBUG' => 'false',
        'DB_CONNECTION' => config('database.default'), 'DB_HOST' => (string) $db['host'], 'DB_PORT' => (string) $db['port'],
        'DB_DATABASE' => (string) $db['database'], 'DB_USERNAME' => (string) $db['username'], 'DB_PASSWORD' => (string) $db['password'], 'DB_URL' => '',
        'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'VAPID_PUBLIC_KEY' => '', 'VAPID_PRIVATE_KEY' => '',
    ]);
    $start = microtime(true) + 1.5; // tempo per avviarsi tutti, poi partono insieme
    $procs = [];
    for ($i = 0; $i < $count; $i++) {
        $cmd = array_merge([PHP_BINARY, base_path('tests/fixtures/stock-worker.php')], array_map('strval', $args), [sprintf('%.4f', $start)]);
        $procs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env) ?: throw new RuntimeException('proc_open');
        $outputs[] = $pipes;
    }
    $results = [];
    foreach ($procs as $i => $proc) {
        $out = trim((string) stream_get_contents($outputs[$i][1]));
        $err = trim((string) stream_get_contents($outputs[$i][2]));
        proc_close($proc);
        $results[] = $out !== '' ? $out : 'errore: '.mb_substr($err, 0, 300);
    }

    return $results;
}

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->main = makeWarehouse();
    $this->part = makePart();
});

it('otto prelievi contemporanei su tre pezzi: ne passano tre, la giacenza non va sotto zero', function () {
    app(Stock::class)->receive($this->part, $this->main, 3, $this->admin);

    $results = raceWorkers(8, ['issue', $this->part->id, $this->main->id, $this->admin->id, 1, 0]);

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

    $results = raceWorkers(6, ['return', $this->part->id, $this->main->id, $this->admin->id, 1, $order->id]);

    expect(array_count_values($results))->toEqualCanonicalizing(['ok' => 2, 'rifiutato' => 4])
        ->and($stock->netIssued($order, $this->part))->toBe(0.0)
        ->and((float) $this->part->fresh()->stock_total)->toBe(10.0);
})->skip(fn () => ! function_exists('proc_open'), 'proc_open non disponibile');
