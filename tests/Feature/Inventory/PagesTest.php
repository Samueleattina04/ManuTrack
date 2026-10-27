<?php

use App\Livewire\Inventory\PartForm;
use App\Livewire\Inventory\Parts;
use App\Livewire\Inventory\PartShow;
use App\Livewire\Inventory\Warehouses;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Part;
use App\Models\Supplier;
use App\Support\Access\Access;
use App\Support\Inventory\Stock;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->keeper = userWithRole(Access::STOREKEEPER);
    $this->main = makeWarehouse();
});

it('crea un ricambio con la giacenza iniziale come primo carico', function () {
    Livewire::actingAs($this->keeper)->test(PartForm::class)
        ->set('form.code', 'CUS-6205')->set('form.name', 'Cuscinetto')->set('form.min_stock', '2,5')
        ->set('initial.warehouse_id', (string) $this->main->id)->set('initial.quantity', '10')->set('initial.unit_cost', '7,50')->set('initial.bin', 'A-1')
        ->call('save')->assertHasNoErrors()->assertRedirect();

    $part = Part::query()->sole();
    expect((float) $part->min_stock)->toBe(2.5)->and((float) $part->stock_total)->toBe(10.0)->and((float) $part->unit_cost)->toBe(7.5)
        ->and($part->levels()->sole()->bin)->toBe('A-1')
        ->and(AuditLog::query()->where('action', 'inventory.part_created')->exists())->toBeTrue();
});

it('il codice è obbligatorio e univoco', function () {
    makePart(['code' => 'DOPPIO']);
    Livewire::actingAs($this->keeper)->test(PartForm::class)
        ->set('form.code', 'DOPPIO')->set('form.name', 'Altro')->call('save')->assertHasErrors('form.code');
    Livewire::actingAs($this->keeper)->test(PartForm::class)
        ->set('form.code', '')->set('form.name', 'Senza codice')->call('save')->assertHasErrors('form.code');
});

it('elenco: filtri per scorta e ricerca', function () {
    $stock = app(Stock::class);
    $ok = makePart(['name' => 'Filtro aria', 'min_stock' => 1]);
    $low = makePart(['name' => 'Cinghia', 'min_stock' => 5]);
    makePart(['name' => 'Fusibile', 'min_stock' => 1]);
    $stock->receive($ok, $this->main, 10, $this->admin);
    $stock->receive($low, $this->main, 2, $this->admin);

    Livewire::actingAs($this->keeper)->test(Parts::class)
        ->assertSee('Filtro aria')->assertSee('Cinghia')->assertSee('Fusibile')
        ->set('stock', 'sotto')->assertSee('Cinghia')->assertSee('Fusibile')->assertDontSee('Filtro aria')
        ->set('stock', 'esauriti')->assertSee('Fusibile')->assertDontSee('Cinghia')
        ->set('stock', '')->set('search', 'cingh')->assertSee('Cinghia')->assertDontSee('Fusibile');
});

it('un magazzino con giacenza non si disattiva', function () {
    app(Stock::class)->receive(makePart(), $this->main, 1, $this->admin);

    Livewire::actingAs($this->keeper)->test(Warehouses::class)
        ->call('edit', $this->main->id)->set('form.is_active', false)->call('save')->assertHasErrors('form.is_active');
    expect($this->main->fresh()->is_active)->toBeTrue();
});

it('riordino: raggruppa per fornitore preferito con la quantità suggerita', function () {
    $supplier = Supplier::query()->create(['name' => 'Fornitore Prova']);
    $part = makePart(['name' => 'Kit guarnizioni', 'min_stock' => 2, 'reorder_qty' => 4]);
    $part->suppliers()->attach($supplier->id, ['supplier_code' => 'FP-1', 'is_preferred' => true]);
    app(Stock::class)->receive($part, $this->main, 1, $this->admin);
    makePart(['name' => 'Ricambio con scorta', 'min_stock' => 0]);

    // 2 + 4 − 1 = 5
    expect($part->fresh()->suggestedOrder())->toBe(5.0);
    $this->actingAs($this->keeper)->get(route('inventory.reorder'))->assertOk()
        ->assertSee('Fornitore Prova')->assertSee('Kit guarnizioni')->assertSee('FP-1')->assertDontSee('Ricambio con scorta');
});

it('movimenti: filtro per tipo', function () {
    $part = makePart(['name' => 'Olio']);
    $stock = app(Stock::class);
    $stock->receive($part, $this->main, 10, $this->admin, reference: 'DDT 42');
    $stock->issue($part, $this->main, 3, $this->admin, note: 'Prelievo di prova');

    $this->actingAs($this->keeper)->get(route('inventory.movements', ['tipo' => 'carico']))->assertOk()
        ->assertSee('DDT 42')->assertDontSee('Prelievo di prova');
    $this->actingAs($this->keeper)->get(route('inventory.movements', ['tipo' => 'prelievo']))->assertOk()
        ->assertSee('Prelievo di prova')->assertDontSee('DDT 42');
});

it('distinta: il ricambio si collega alla macchina e compare nella sua scheda', function () {
    $part = makePart(['name' => 'Sensore induttivo']);
    $asset = Asset::factory()->create();

    Livewire::actingAs($this->keeper)->test(PartShow::class, ['part' => $part])
        ->set('assetToAdd', (string) $asset->id)->set('assetQty', '2')->call('addAsset')->assertHasNoErrors();
    expect((float) $asset->parts()->sole()->pivot->quantity)->toBe(2.0);

    $tech = userWithRole(Access::TECHNICIAN);
    $this->actingAs($tech)->get(route('assets.show', $asset))->assertSee('Sensore induttivo');
    $this->actingAs(userWithRole(Access::REQUESTER))->get(route('assets.show', $asset))->assertDontSee('Sensore induttivo');
});

it('panoramica: il magazziniere vede i ricambi sotto scorta', function () {
    makePart(['name' => 'Ricambio esaurito', 'min_stock' => 2]);

    $this->actingAs($this->keeper)->get('/')->assertOk()->assertSee(__('app.home.low_stock'))->assertSee('Ricambio esaurito');
    $this->actingAs(userWithRole(Access::TECHNICIAN))->get('/')->assertOk()->assertDontSee(__('app.home.low_stock'));
});
