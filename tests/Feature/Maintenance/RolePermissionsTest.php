<?php

/*
 * Matrice dei permessi per ruolo su segnalazioni e ordini di lavoro, con i
 * permessi predefiniti di ogni ruolo. Ogni riga dice cosa può fare il ruolo
 * e il test verifica sia il "sì" sia il "no", sulle pagine e sulle azioni.
 */

use App\Actions\Maintenance\TransitionWorkOrder;
use App\Actions\Maintenance\TriageWorkRequest;
use App\Enums\WorkOrderStatus;
use App\Livewire\Requests\Show as RequestShow;
use App\Livewire\WorkOrders\Show as OrderShow;
use App\Models\User;
use App\Support\Access\Access;
use Livewire\Livewire;

/** ruolo => capacità attese */
dataset('roles', [
    'amministratore' => [Access::ADMIN, ['report' => true, 'see_all_requests' => true, 'triage' => true, 'orders' => true, 'create_order' => true, 'execute_unassigned' => true, 'take' => true, 'assign' => true, 'close' => true, 'costs' => true, 'comment' => true, 'settings' => true]],
    'responsabile' => [Access::MANAGER, ['report' => true, 'see_all_requests' => true, 'triage' => true, 'orders' => true, 'create_order' => true, 'execute_unassigned' => true, 'take' => true, 'assign' => true, 'close' => true, 'costs' => true, 'comment' => true, 'settings' => true]],
    'tecnico' => [Access::TECHNICIAN, ['report' => true, 'see_all_requests' => true, 'triage' => false, 'orders' => true, 'create_order' => true, 'execute_unassigned' => false, 'take' => true, 'assign' => false, 'close' => false, 'costs' => false, 'comment' => true, 'settings' => false]],
    'magazziniere' => [Access::STOREKEEPER, ['report' => true, 'see_all_requests' => false, 'triage' => false, 'orders' => true, 'create_order' => false, 'execute_unassigned' => false, 'take' => false, 'assign' => false, 'close' => false, 'costs' => false, 'comment' => false, 'settings' => false]],
    'richiedente' => [Access::REQUESTER, ['report' => true, 'see_all_requests' => false, 'triage' => false, 'orders' => false, 'create_order' => false, 'execute_unassigned' => false, 'take' => false, 'assign' => false, 'close' => false, 'costs' => false, 'comment' => false, 'settings' => false]],
    'osservatore' => [Access::VIEWER, ['report' => false, 'see_all_requests' => true, 'triage' => false, 'orders' => true, 'create_order' => false, 'execute_unassigned' => false, 'take' => false, 'assign' => false, 'close' => false, 'costs' => true, 'comment' => false, 'settings' => false]],
]);

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->author = userWithRole(Access::REQUESTER);
    $this->foreign = makeRequest($this->author);
});

function actor(string $role, User $admin): User
{
    return $role === Access::ADMIN ? $admin : userWithRole($role);
}

it('segnalare: pagina e invio', function (string $role, array $can) {
    $user = actor($role, $this->admin);

    $this->actingAs($user)->get(route('requests.create'))->assertStatus($can['report'] ? 200 : 403);
    $this->actingAs($user)->post(route('requests.store'), reportPayload(), ['X-ManuTrack' => '1', 'Accept' => 'application/json'])
        ->assertStatus($can['report'] ? 201 : 403);
})->with('roles');

it('vedere le segnalazioni degli altri', function (string $role, array $can) {
    $user = actor($role, $this->admin);

    $this->actingAs($user)->get(route('requests.show', $this->foreign))->assertStatus($can['see_all_requests'] ? 200 : 403);
    $this->actingAs($user)->get(route('requests.index'))->assertOk()
        ->{$can['see_all_requests'] ? 'assertSee' : 'assertDontSee'}($this->foreign->number);

    // Le proprie si vedono sempre (chi può segnalare)
    if ($can['report']) {
        $own = makeRequest($user, ['description' => 'Segnalazione personale']);
        $this->actingAs($user)->get(route('requests.show', $own))->assertOk();
    }
})->with('roles');

it('valutare le segnalazioni', function (string $role, array $can) {
    $user = actor($role, $this->admin);
    expect($user->can('triage', $this->foreign))->toBe($can['triage']);

    // Chi può aprire la scheda ma non valutare non vede i pulsanti e non può forzare l'azione
    if ($user->can('view', $this->foreign)) {
        $page = Livewire::actingAs($user)->test(RequestShow::class, ['workRequest' => $this->foreign]);
        $page->{$can['triage'] ? 'assertSee' : 'assertDontSee'}(__('maintenance.request.triage'));
        $page->call('start', 'reject')->{$can['triage'] ? 'assertHasNoErrors' : 'assertForbidden'}();
        Livewire::actingAs($user)->test(RequestShow::class, ['workRequest' => $this->foreign])
            ->set('note', 'Non è un guasto')->call('reject')->{$can['triage'] ? 'assertHasNoErrors' : 'assertForbidden'}();
        expect($this->foreign->fresh()->status->value)->toBe($can['triage'] ? 'respinta' : 'nuova');
    }
})->with('roles');

it('ordini di lavoro: elenco, creazione, impostazioni', function (string $role, array $can) {
    $user = actor($role, $this->admin);

    $this->actingAs($user)->get(route('work-orders.index'))->assertStatus($can['orders'] ? 200 : 403);
    $this->actingAs($user)->get(route('work-orders.create'))->assertStatus($can['create_order'] ? 200 : 403);
    $this->actingAs($user)->get(route('work-orders.settings'))->assertStatus($can['settings'] ? 200 : 403);
})->with('roles');

it('lavorare su un ordine non assegnato, prenderlo in carico, assegnarlo', function (string $role, array $can) {
    $user = actor($role, $this->admin);
    $order = makeOrder($this->admin);

    expect($user->can('execute', $order))->toBe($can['execute_unassigned'])
        ->and($user->can('take', $order))->toBe($can['take'])
        ->and($user->can('assign', $order))->toBe($can['assign'])
        ->and($user->can('comment', $order))->toBe($can['comment'])
        ->and($user->can('viewCosts', $order))->toBe($can['costs']);

    if (! $can['orders']) {
        $this->actingAs($user)->get(route('work-orders.show', $order))->assertForbidden();

        return;
    }

    $page = Livewire::actingAs($user)->test(OrderShow::class, ['workOrder' => $order]);
    $can['execute_unassigned'] ? $page->call('move', 'in_corso')->assertHasNoErrors() : $page->call('move', 'in_corso')->assertForbidden();
    Livewire::actingAs($user)->test(OrderShow::class, ['workOrder' => $order->fresh()])->call('editAssignees')
        ->{$can['assign'] ? 'assertHasNoErrors' : 'assertForbidden'}();

    // Costi: la sezione compare solo a chi può vederli
    $this->actingAs($user)->get(route('work-orders.show', $order))->assertOk()
        ->{$can['costs'] ? 'assertSee' : 'assertDontSee'}(__('maintenance.costs.title'));
})->with('roles');

it('chiudere un ordine completato', function (string $role, array $can) {
    $user = actor($role, $this->admin);
    $tech = userWithRole(Access::TECHNICIAN);
    $order = makeOrder($this->admin, [$tech->id]);
    app(TransitionWorkOrder::class)->handle($order, WorkOrderStatus::InProgress, $tech);
    app(TransitionWorkOrder::class)->handle($order->fresh(), WorkOrderStatus::Completed, $tech, ['completion_notes' => 'Fatto']);

    expect($user->can('transition', [$order->fresh(), WorkOrderStatus::Closed]))->toBe($can['close'])
        ->and($user->can('transition', [$order->fresh(), WorkOrderStatus::InProgress]))->toBe($can['close']);
})->with('roles');

it('il tecnico lavora sugli ordini assegnati a lui, non su quelli degli altri', function () {
    $mine = userWithRole(Access::TECHNICIAN);
    $other = userWithRole(Access::TECHNICIAN);
    $order = makeOrder($this->admin, [$other->id]);

    expect($mine->can('execute', $order))->toBeFalse()
        ->and($mine->can('take', $order))->toBeFalse() // ha già qualcuno
        ->and($other->can('execute', $order))->toBeTrue();

    Livewire::actingAs($mine)->test(OrderShow::class, ['workOrder' => $order])->call('move', 'in_corso')->assertForbidden();
    Livewire::actingAs($mine)->test(OrderShow::class, ['workOrder' => $order])->call('startTimer')->assertForbidden();
    Livewire::actingAs($other)->test(OrderShow::class, ['workOrder' => $order])->call('move', 'in_corso')->assertHasNoErrors();
    expect($order->fresh()->status)->toBe(WorkOrderStatus::InProgress);
});

it('il tecnico prende in carico un ordine libero e diventa assegnatario', function () {
    $tech = userWithRole(Access::TECHNICIAN);
    $order = makeOrder($this->admin);

    Livewire::actingAs($tech)->test(OrderShow::class, ['workOrder' => $order])->call('take');

    expect($order->fresh()->assignees->pluck('id')->all())->toBe([$tech->id])
        ->and($order->fresh()->status)->toBe(WorkOrderStatus::Planned)
        ->and($tech->can('execute', $order->fresh()))->toBeTrue();
});

it('non si assegna un ordine a chi non può eseguirlo', function () {
    $order = makeOrder($this->admin);

    Livewire::actingAs($this->admin)->test(OrderShow::class, ['workOrder' => $order])
        ->call('editAssignees')->set('assignees', [(string) $this->author->id])->call('saveAssignees')
        ->assertHasErrors('assignees');
    expect($order->fresh()->assignees)->toBeEmpty();
});

it('il richiedente vede l\'esito della sua segnalazione ma non l\'ordine né le foto dell\'ordine', function () {
    $tech = userWithRole(Access::TECHNICIAN);
    $order = app(TriageWorkRequest::class)->accept($this->foreign, $this->admin, ['assignees' => [$tech->id]]);

    $this->actingAs($this->author)->get(route('requests.show', $this->foreign))->assertOk()
        ->assertSee($order->number)->assertDontSee(__('maintenance.request.open_order'));
    $this->actingAs($this->author)->get(route('work-orders.show', $order))->assertForbidden();
});

it('nessun ruolo può saltare il ciclo di vita, nemmeno l\'amministratore', function () {
    $order = makeOrder($this->admin);
    // L'amministratore passa ogni permesso, ma un ordine aperto non si chiude direttamente
    Livewire::actingAs($this->admin)->test(OrderShow::class, ['workOrder' => $order])->call('move', 'chiuso')->assertHasErrors('transition.status');
    expect($order->fresh()->status)->toBe(WorkOrderStatus::Open);
});

it('la barra in basso del telefono mostra solo voci accessibili', function () {
    $this->actingAs($this->author)->get(route('home'))->assertOk()
        ->assertSee(route('requests.index'))->assertDontSee(route('work-orders.index'));
    $this->actingAs(userWithRole(Access::TECHNICIAN))->get(route('home'))->assertSee(route('work-orders.index'));
});
