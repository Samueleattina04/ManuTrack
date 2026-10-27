<?php

use App\Livewire\Kpi\Dashboard;
use App\Support\Access\Access;
use Livewire\Livewire;

dataset('kpi_roles', [
    'amministratore' => [Access::ADMIN, true, true],
    'responsabile' => [Access::MANAGER, true, true],
    'tecnico' => [Access::TECHNICIAN, false, false],
    'magazziniere' => [Access::STOREKEEPER, true, false],
    'richiedente' => [Access::REQUESTER, false, false],
    'osservatore' => [Access::VIEWER, true, true],
]);

beforeEach(function () {
    $this->admin = installManuTrack();
});

it('dashboard e costi per ruolo', function (string $role, bool $view, bool $costs) {
    $user = $role === Access::ADMIN ? $this->admin : userWithRole($role);
    $page = $this->actingAs($user)->get(route('kpi'));
    $page->assertStatus($view ? 200 : 403);
    if ($view) {
        $costs ? $page->assertSee(__('kpi.costs.none')) : $page->assertDontSee(__('kpi.costs.none'));
    }
})->with('kpi_roles');

it('senza dati mostra "dati insufficienti", non numeri', function () {
    $this->actingAs($this->admin)->get(route('kpi'))
        ->assertSee(__('kpi.insufficient'))
        ->assertSee(trans_choice('kpi.mttr.need', 0, ['n' => 0, 'min' => 3]));
});

it('periodi rapidi e periodo non valido', function () {
    $this->travelTo('2026-04-10 12:00:00');
    Livewire::actingAs($this->admin)->test(Dashboard::class)
        ->call('choose', 'mese_prec')->assertSet('from', '2026-03-01')->assertSet('to', '2026-03-31')
        ->set('preset', 'scelta')->set('from', '2026-05-01')->set('to', '2026-05-31')
        ->assertSee(__('kpi.errors.future'))
        ->set('from', '2026-03-31')->set('to', '2026-03-01')->assertSee(__('kpi.errors.order'));
});
