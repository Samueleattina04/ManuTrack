<?php

/*
 * Pagine della preventiva e permessi per ruolo.
 */

use App\Livewire\Preventive\Calendar;
use App\Livewire\Preventive\Form;
use App\Livewire\Preventive\Index;
use App\Livewire\Preventive\Show;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\MaintenancePlan;
use App\Models\WorkOrder;
use App\Support\Access\Access;
use Livewire\Livewire;

dataset('preventive_roles', [
    'amministratore' => [Access::ADMIN, ['view' => true, 'manage' => true]],
    'responsabile' => [Access::MANAGER, ['view' => true, 'manage' => true]],
    'tecnico' => [Access::TECHNICIAN, ['view' => true, 'manage' => false]],
    'magazziniere' => [Access::STOREKEEPER, ['view' => false, 'manage' => false]],
    'richiedente' => [Access::REQUESTER, ['view' => false, 'manage' => false]],
    'osservatore' => [Access::VIEWER, ['view' => true, 'manage' => false]],
]);

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->plan = makePlan(['title' => 'Ingrassaggio guide', 'next_due_on' => today()->addDays(20)->toDateString()]);
});

it('pagine per ruolo', function (string $role, array $can) {
    $user = $role === Access::ADMIN ? $this->admin : userWithRole($role);

    foreach ([route('preventive.index'), route('preventive.calendar'), route('preventive.show', $this->plan)] as $url) {
        $this->actingAs($user)->get($url)->assertStatus($can['view'] ? 200 : 403);
    }
    foreach ([route('preventive.create'), route('preventive.edit', $this->plan)] as $url) {
        $this->actingAs($user)->get($url)->assertStatus($can['manage'] ? 200 : 403);
    }
})->with('preventive_roles');

it('azioni sulla scheda solo per chi gestisce i piani', function (string $role, array $can) {
    $user = $role === Access::ADMIN ? $this->admin : userWithRole($role);
    if (! $can['view']) {
        expect($user->can('preventive.manage'))->toBeFalse();

        return;
    }
    $page = Livewire::actingAs($user)->test(Show::class, ['plan' => $this->plan]);
    foreach ([['generate'], ['removePart', 1], ['delete']] as $call) {
        $test = (clone $page)->call(...$call);
        $can['manage'] ? $test->assertOk() : $test->assertForbidden();
        if ($call[0] === 'delete' && $can['manage']) {
            return;
        }
    }
})->with('preventive_roles');

it('crea un piano a tempo dal modulo, partendo dalla macchina', function () {
    $asset = Asset::factory()->create();

    Livewire::actingAs($this->admin)->withQueryParams(['macchina' => $asset->id])->test(Form::class)
        ->assertSet('form.asset_id', (string) $asset->id)
        ->set('form.title', 'Controllo cinghie')->set('form.interval_value', '3')->set('form.interval_unit', 'mesi')
        ->set('form.next_due_on', '2027-01-31')->set('form.schedule_mode', 'fisso')
        ->call('save')->assertHasNoErrors()->assertRedirect();

    $plan = MaintenancePlan::query()->where('title', 'Controllo cinghie')->sole();
    expect($plan->asset_id)->toBe($asset->id)->and($plan->start_on->toDateString())->toBe('2027-01-31')
        ->and($plan->frequencyLabel())->toBe('Ogni 3 mesi')
        ->and(AuditLog::query()->where('action', 'preventive.plan_created')->exists())->toBeTrue();
});

it('a contatore: propone la soglia e rifiuta il contatore di un\'altra macchina', function () {
    $asset = Asset::factory()->create();
    $meter = $asset->meters()->create(['name' => 'Ore', 'unit' => 'h']);
    $meter->forceFill(['current_value' => 1200])->save();
    $other = Asset::factory()->create()->meters()->create(['name' => 'Ore', 'unit' => 'h']);

    $form = Livewire::actingAs($this->admin)->test(Form::class)
        ->set('form.title', 'Cambio olio')->set('form.asset_id', (string) $asset->id)->set('form.trigger', 'contatore')
        ->set('form.meter_interval', '500')->set('form.meter_id', (string) $meter->id)
        ->assertSet('form.next_meter_due', '1.700');

    $form->set('form.meter_id', (string) $other->id)->call('save')->assertHasErrors('form.meter_id');
    $form->set('form.meter_id', (string) $meter->id)->call('save')->assertHasNoErrors();
    expect((float) MaintenancePlan::query()->where('title', 'Cambio olio')->value('next_meter_due'))->toBe(1700.0);
});

it('modificando la data di un piano a date fisse cambia il punto di partenza; il registro salva prima e dopo', function () {
    Livewire::actingAs($this->admin)->test(Form::class, ['plan' => $this->plan])
        ->set('form.next_due_on', '2027-03-15')->set('form.title', 'Ingrassaggio guide e colonne')->call('save')->assertHasNoErrors();

    $plan = $this->plan->fresh();
    expect($plan->start_on->toDateString())->toBe('2027-03-15');
    $log = AuditLog::query()->where('action', 'preventive.plan_updated')->sole();
    expect($log->changes['title'])->toBe(['Ingrassaggio guide', 'Ingrassaggio guide e colonne']);
});

it('l\'assegnatario deve poter eseguire gli ordini', function () {
    Livewire::actingAs($this->admin)->test(Form::class, ['plan' => $this->plan])
        ->set('form.assignee_id', (string) userWithRole(Access::REQUESTER)->id)->call('save')->assertHasErrors('form.assignee_id');
});

it('"Genera ora" crea l\'ordine anche prima dell\'anticipo, e non un secondo', function () {
    Livewire::actingAs($this->admin)->test(Show::class, ['plan' => $this->plan])->call('generate')->assertRedirect();
    $order = WorkOrder::query()->sole();
    expect($order->maintenance_plan_id)->toBe($this->plan->id)->and($order->creator->id)->toBe($this->admin->id);

    Livewire::actingAs($this->admin)->test(Show::class, ['plan' => $this->plan->fresh()])->call('generate')->assertNoRedirect();
    expect(WorkOrder::query()->count())->toBe(1);
});

it('ricambi previsti e copia su un\'altra macchina', function () {
    $part = makePart(['name' => 'Grasso EP2']);
    $other = Asset::factory()->create(['code' => 'ALTRA-1']);

    Livewire::actingAs($this->admin)->test(Show::class, ['plan' => $this->plan])
        ->set('partToAdd', (string) $part->id)->set('partQty', '0,5')->call('addPart')->assertHasNoErrors()
        ->set('copyTo', (string) $other->id)->call('duplicate')->assertRedirect();

    $copy = MaintenancePlan::query()->where('asset_id', $other->id)->sole();
    expect((float) $this->plan->parts()->sole()->pivot->quantity)->toBe(0.5)
        ->and($copy->parts()->sole()->id)->toBe($part->id)
        ->and($copy->current_work_order_id)->toBeNull();
});

it('elenco: filtri per stato e rispetto dei piani', function () {
    makePlan(['title' => 'Piano scaduto', 'next_due_on' => today()->subDays(3)->toDateString()]);
    makePlan(['title' => 'Piano sospeso', 'is_active' => false]);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSee('Ingrassaggio guide')->assertSee('Piano scaduto')->assertDontSee('Piano sospeso')
        ->set('state', 'overdue')->assertSee('Piano scaduto')->assertDontSee('Ingrassaggio guide')
        ->set('state', '')->set('inactive', true)->assertSee('Piano sospeso');
});

it('calendario: scadenze previste e ordini del mese; "solo i miei"', function () {
    $tech = userWithRole(Access::TECHNICIAN);
    $plan = makePlan(['title' => 'Controllo settimanale', 'interval_value' => 1, 'interval_unit' => 'settimane', 'next_due_on' => '2026-12-07', 'assignee_id' => $tech->id]);

    Livewire::actingAs($tech)->test(Calendar::class)->set('month', '2026-12')
        ->assertSee('Controllo settimanale')->assertSee('Ingrassaggio guide')
        ->set('mine', true)->assertSee('Controllo settimanale')->assertDontSee('Ingrassaggio guide');
    expect($plan->id)->toBeInt();
});

it('la scheda macchina mostra i piani a chi può vederli', function () {
    $asset = $this->plan->asset;
    $this->actingAs(userWithRole(Access::TECHNICIAN))->get(route('assets.show', $asset))->assertSee('Ingrassaggio guide');
    $this->actingAs(userWithRole(Access::REQUESTER))->get(route('assets.show', $asset))->assertDontSee('Ingrassaggio guide');
});

it('panoramica: chi gestisce la preventiva vede i piani da fare', function () {
    makePlan(['title' => 'Piano scaduto', 'next_due_on' => today()->subDay()->toDateString()]);
    $this->actingAs(userWithRole(Access::MANAGER))->get('/')->assertSee(__('app.home.due_plans'))->assertSee('Piano scaduto');
    $this->actingAs(userWithRole(Access::TECHNICIAN))->get('/')->assertDontSee(__('app.home.due_plans'));
});
