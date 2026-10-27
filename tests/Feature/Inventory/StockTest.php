<?php

use App\Enums\MovementType;
use App\Enums\WorkOrderStatus;
use App\Jobs\SendPushMessage;
use App\Models\AuditLog;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Support\Access\Access;
use App\Support\Inventory\Stock;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->stock = app(Stock::class);
    $this->main = makeWarehouse();
    $this->part = makePart();
});

it('il carico calcola il costo medio ponderato', function () {
    $this->stock->receive($this->part, $this->main, 10, $this->admin, '10,00');
    $this->stock->receive($this->part, $this->main, 30, $this->admin, '14');

    $part = $this->part->fresh();
    // (10 × 10 + 30 × 14) / 40 = 13
    expect((float) $part->unit_cost)->toBe(13.0)->and((float) $part->stock_total)->toBe(40.0);

    // Un carico senza costo non cambia la media
    $this->stock->receive($this->part, $this->main, 10, $this->admin);
    expect((float) $this->part->fresh()->unit_cost)->toBe(13.0);
});

it('il prelievo esce al costo medio e registra la giacenza risultante', function () {
    $this->stock->receive($this->part, $this->main, 5, $this->admin, 8);
    $m = $this->stock->issue($this->part, $this->main, 2, $this->admin);

    expect((float) $m->quantity)->toBe(-2.0)->and((float) $m->unit_cost)->toBe(8.0)
        ->and((float) $m->balance_after)->toBe(3.0)->and($m->value())->toBe(16.0);
});

it('la giacenza non va mai sotto zero', function () {
    $this->stock->receive($this->part, $this->main, 3, $this->admin);

    expect(fn () => $this->stock->issue($this->part, $this->main, 4, $this->admin))->toThrow(ValidationException::class);
    expect((float) $this->part->fresh()->stock_total)->toBe(3.0)
        ->and(StockMovement::query()->count())->toBe(1);
});

it('due prelievi con dati vecchi non svuotano due volte lo stesso pezzo', function () {
    $this->stock->receive($this->part, $this->main, 1, $this->admin);
    // Due richieste hanno caricato il ricambio quando la giacenza era 1
    $first = $this->part->fresh();
    $second = $this->part->fresh();

    $this->stock->issue($first, $this->main, 1, $this->admin);
    expect(fn () => $this->stock->issue($second, $this->main, 1, $this->admin))->toThrow(ValidationException::class);
    expect((float) StockLevel::query()->sole()->quantity)->toBe(0.0);
});

it('quantità non valide sono rifiutate', function (mixed $qty) {
    expect(fn () => $this->stock->receive($this->part, $this->main, $qty, $this->admin))->toThrow(ValidationException::class);
})->with([0, -1, 'abc', '', 20_000_000]);

it('accetta quantità decimali all\'italiana', function () {
    $this->stock->receive($this->part, $this->main, '2,5', $this->admin);
    expect((float) $this->part->fresh()->stock_total)->toBe(2.5);
});

it('il reso torna al costo di uscita e non supera il prelevato', function () {
    $order = makeOrder($this->admin);
    $order->forceFill(['status' => WorkOrderStatus::InProgress])->save();
    $this->stock->receive($this->part, $this->main, 10, $this->admin, 5);
    $this->stock->issue($this->part, $this->main, 4, $this->admin, $order);
    $this->stock->receive($this->part, $this->main, 10, $this->admin, 9); // il costo medio sale a 7,5

    $back = $this->stock->giveBack($this->part, $this->main, 1, $this->admin, $order);
    expect((float) $back->unit_cost)->toBe(5.0)
        ->and($this->stock->netIssued($order, $this->part))->toBe(3.0)
        ->and($order->fresh()->partsCost())->toBe(15.0);

    expect(fn () => $this->stock->giveBack($this->part, $this->main, 4, $this->admin, $order))->toThrow(ValidationException::class);
});

it('non si preleva per un ordine chiuso o annullato', function (WorkOrderStatus $status) {
    $order = makeOrder($this->admin);
    $order->forceFill(['status' => $status])->save();
    $this->stock->receive($this->part, $this->main, 10, $this->admin);

    expect(fn () => $this->stock->issue($this->part, $this->main, 1, $this->admin, $order))->toThrow(ValidationException::class);
})->with([WorkOrderStatus::Closed, WorkOrderStatus::Cancelled]);

it('la rettifica porta la giacenza al contato, con motivo e registro attività', function () {
    $this->stock->receive($this->part, $this->main, 10, $this->admin);

    expect(fn () => $this->stock->adjust($this->part, $this->main, 8, $this->admin, ''))->toThrow(ValidationException::class);
    expect(fn () => $this->stock->adjust($this->part, $this->main, 10, $this->admin, 'Conta annuale'))->toThrow(ValidationException::class);

    $m = $this->stock->adjust($this->part, $this->main, '8', $this->admin, 'Conta annuale');
    expect($m->type)->toBe(MovementType::Adjustment)->and((float) $m->quantity)->toBe(-2.0)
        ->and((float) $this->part->fresh()->stock_total)->toBe(8.0)
        ->and(AuditLog::query()->where('action', 'inventory.adjusted')->exists())->toBeTrue();
});

it('il trasferimento sposta la giacenza senza cambiare il totale', function () {
    $line = makeWarehouse('Armadio di linea');
    $this->stock->receive($this->part, $this->main, 10, $this->admin);
    $this->stock->transfer($this->part, $this->main, $line, 4, $this->admin);

    $levels = StockLevel::query()->pluck('quantity', 'warehouse_id')->map(fn ($q) => (float) $q);
    expect($levels[$this->main->id])->toBe(6.0)->and($levels[$line->id])->toBe(4.0)
        ->and((float) $this->part->fresh()->stock_total)->toBe(10.0)
        ->and(StockMovement::query()->where('type', 'trasferimento')->distinct()->count('transfer_group'))->toBe(1);

    expect(fn () => $this->stock->transfer($this->part, $this->main, $this->main, 1, $this->admin))->toThrow(ValidationException::class);
    expect(fn () => $this->stock->transfer($this->part, $line, $this->main, 5, $this->admin))->toThrow(ValidationException::class);
    expect(StockMovement::query()->count())->toBe(3);
});

it('in un magazzino disattivato non entra nulla', function () {
    $closed = makeWarehouse('Vecchio', active: false);
    expect(fn () => $this->stock->receive($this->part, $closed, 1, $this->admin))->toThrow(ValidationException::class);
});

it('scendendo alla scorta minima avvisa una volta chi gestisce il magazzino', function () {
    config(['manutrack.webpush.public_key' => 'pubblica', 'manutrack.webpush.private_key' => 'privata']);
    Bus::fake([SendPushMessage::class]);
    $keeper = userWithRole(Access::STOREKEEPER);
    $tech = userWithRole(Access::TECHNICIAN);
    $part = makePart(['min_stock' => 3]);
    $this->stock->receive($part, $this->main, 5, $keeper);

    $this->stock->issue($part, $this->main, 1, $tech); // 4: sopra il minimo
    Bus::assertNotDispatched(SendPushMessage::class);

    $this->stock->issue($part, $this->main, 1, $tech); // 3: alla scorta minima
    Bus::assertDispatched(SendPushMessage::class, fn ($job) => in_array($keeper->id, $job->userIds, true) && ! in_array($tech->id, $job->userIds, true));

    $this->stock->issue($part, $this->main, 1, $tech); // già sotto: nessun nuovo avviso
    Bus::assertDispatchedTimes(SendPushMessage::class, 1);
});
