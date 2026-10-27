<?php

/*
 * Matrice dei permessi per ruolo sul magazzino, con i permessi predefiniti:
 * vedere, prelevare, gestire (carichi, rettifiche, anagrafiche) e vedere i costi.
 */

use App\Enums\WorkOrderStatus;
use App\Livewire\Inventory\Import;
use App\Livewire\Inventory\PartShow;
use App\Livewire\Inventory\Suppliers;
use App\Livewire\Inventory\Warehouses;
use App\Livewire\WorkOrders\Parts as OrderParts;
use App\Support\Access\Access;
use App\Support\Inventory\Stock;
use Livewire\Livewire;

dataset('inventory_roles', [
    'amministratore' => [Access::ADMIN, ['view' => true, 'move' => true, 'manage' => true, 'costs' => true]],
    'responsabile' => [Access::MANAGER, ['view' => true, 'move' => true, 'manage' => false, 'costs' => true]],
    'tecnico' => [Access::TECHNICIAN, ['view' => true, 'move' => true, 'manage' => false, 'costs' => false]],
    'magazziniere' => [Access::STOREKEEPER, ['view' => true, 'move' => true, 'manage' => true, 'costs' => true]],
    'richiedente' => [Access::REQUESTER, ['view' => false, 'move' => false, 'manage' => false, 'costs' => false]],
    'osservatore' => [Access::VIEWER, ['view' => true, 'move' => false, 'manage' => false, 'costs' => true]],
]);

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->main = makeWarehouse();
    $this->part = makePart(['min_stock' => 1]);
    app(Stock::class)->receive($this->part, $this->main, 10, $this->admin, 987.65);
});

function inventoryActor(string $role, $admin)
{
    return $role === Access::ADMIN ? $admin : userWithRole($role);
}

it('pagine di consultazione', function (string $role, array $can) {
    $user = inventoryActor($role, $this->admin);
    foreach (['inventory.parts.index', 'inventory.movements', 'inventory.suppliers'] as $route) {
        $this->actingAs($user)->get(route($route))->assertStatus($can['view'] ? 200 : 403);
    }
    $this->actingAs($user)->get(route('inventory.parts.show', $this->part))->assertStatus($can['view'] ? 200 : 403);
})->with('inventory_roles');

it('pagine di gestione', function (string $role, array $can) {
    $user = inventoryActor($role, $this->admin);
    foreach (['inventory.parts.create', 'inventory.warehouses', 'inventory.reorder', 'inventory.import', 'inventory.import.template'] as $route) {
        $this->actingAs($user)->get(route($route))->assertStatus($can['manage'] ? 200 : 403);
    }
    $this->actingAs($user)->get(route('inventory.parts.edit', $this->part))->assertStatus($can['manage'] ? 200 : 403);
})->with('inventory_roles');

it('prelievo dalla scheda del ricambio', function (string $role, array $can) {
    $user = inventoryActor($role, $this->admin);
    if (! $can['view']) {
        $this->actingAs($user);
        Livewire::test(PartShow::class, ['part' => $this->part])->assertForbidden();

        return;
    }

    $test = Livewire::actingAs($user)->test(PartShow::class, ['part' => $this->part])->call('start', 'issue');
    if (! $can['move']) {
        $test->assertForbidden();

        return;
    }
    $test->set('move.warehouse_id', (string) $this->main->id)->set('move.quantity', '2')->call('submit')->assertHasNoErrors();
    expect((float) $this->part->fresh()->stock_total)->toBe(8.0);
})->with('inventory_roles');

it('carico, rettifica e trasferimento solo per chi gestisce', function (string $role, array $can) {
    $user = inventoryActor($role, $this->admin);
    if (! $can['view']) {
        expect(true)->toBeTrue();

        return;
    }
    foreach (['receive', 'adjust', 'transfer'] as $action) {
        $test = Livewire::actingAs($user)->test(PartShow::class, ['part' => $this->part])->call('start', $action);
        $can['manage'] ? $test->assertOk()->assertSet('action', $action) : $test->assertForbidden();
    }
    $test = Livewire::actingAs($user)->test(PartShow::class, ['part' => $this->part])->call('editSupplier');
    $can['manage'] ? $test->assertOk() : $test->assertForbidden();
})->with('inventory_roles');

it('costi visibili solo a chi può vederli', function (string $role, array $can) {
    $user = inventoryActor($role, $this->admin);
    if (! $can['view']) {
        expect($user->can('inventory.view_costs'))->toBeFalse();

        return;
    }
    $page = $this->actingAs($user)->get(route('inventory.parts.show', $this->part));
    $can['costs'] ? $page->assertSee('€ 987,65') : $page->assertDontSee('€ 987,65');
    $list = $this->actingAs($user)->get(route('inventory.parts.index'));
    $can['costs'] ? $list->assertSee('€ 987,65') : $list->assertDontSee('€ 987,65');
})->with('inventory_roles');

it('anagrafiche: fornitori e magazzini', function (string $role, array $can) {
    $user = inventoryActor($role, $this->admin);
    if (! $can['view']) {
        expect(true)->toBeTrue();

        return;
    }
    $test = Livewire::actingAs($user)->test(Suppliers::class)->call('edit');
    $can['manage'] ? $test->assertOk() : $test->assertForbidden();

    $this->actingAs($user);
    $warehouses = Livewire::test(Warehouses::class);
    $can['manage'] ? $warehouses->assertOk() : $warehouses->assertForbidden();
    $import = Livewire::test(Import::class);
    $can['manage'] ? $import->assertOk() : $import->assertForbidden();
})->with('inventory_roles');

it('prelievo dall\'ordine: serve poter eseguire l\'ordine e muovere il magazzino', function () {
    $tech = userWithRole(Access::TECHNICIAN);
    $other = userWithRole(Access::TECHNICIAN);
    $keeper = userWithRole(Access::STOREKEEPER);
    $order = makeOrder($this->admin, [$tech->id]);
    $order->forceFill(['status' => WorkOrderStatus::InProgress])->save();

    // Il tecnico assegnato preleva
    Livewire::actingAs($tech)->test(OrderParts::class, ['workOrder' => $order])
        ->call('open')->call('choose', $this->part->id)
        ->assertSet('warehouseId', (string) $this->main->id)
        ->set('quantity', '3')->call('issue')->assertHasNoErrors()->assertDispatched('parts-changed');
    expect(app(Stock::class)->netIssued($order, $this->part))->toBe(3.0);

    // Un tecnico non assegnato e il magazziniere (che non esegue ordini) no
    Livewire::actingAs($other)->test(OrderParts::class, ['workOrder' => $order])->call('open')->assertForbidden();
    Livewire::actingAs($keeper)->test(OrderParts::class, ['workOrder' => $order])->call('open')->assertForbidden();

    // Il reso
    Livewire::actingAs($tech)->test(OrderParts::class, ['workOrder' => $order])
        ->call('startReturn', $this->part->id)->assertSet('returnQuantity', '3')
        ->set('returnQuantity', '5')->call('giveBack')->assertHasErrors('returnQuantity')
        ->set('returnQuantity', '1')->call('giveBack')->assertHasNoErrors();
    expect(app(Stock::class)->netIssued($order, $this->part))->toBe(2.0);

    // Ordine chiuso: niente più prelievi
    $order->forceFill(['status' => WorkOrderStatus::Closed])->save();
    Livewire::actingAs($tech)->test(OrderParts::class, ['workOrder' => $order->fresh()])->call('open')->assertForbidden();
});

it('il costo dei ricambi entra nei costi dell\'ordine', function () {
    $order = makeOrder($this->admin, [$this->admin->id]);
    $order->forceFill(['status' => WorkOrderStatus::InProgress])->save();
    app(Stock::class)->issue($this->part, $this->main, 2, $this->admin, $order);

    $costs = $order->fresh()->costs();
    expect($costs['parts'])->toBe(1975.3)->and($costs['total'])->toBe(1975.3 + $costs['labor'] + $costs['external']);
    $this->actingAs($this->admin)->get(route('work-orders.show', $order))->assertSee('€ 1.975,30');
});
