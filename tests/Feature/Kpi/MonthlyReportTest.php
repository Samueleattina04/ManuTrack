<?php

use App\Enums\NotificationEvent;
use App\Models\UserNotification;
use App\Models\WorkOrder;
use App\Support\Access\Access;
use App\Support\Inventory\Stock;
use App\Support\Kpi\MonthlyReport;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->admin = installManuTrack();
});

/** Ordine concluso in una data precisa */
function concludedOrder($admin, string $title, string $completedAt, string $status = 'chiuso'): WorkOrder
{
    $o = makeOrder($admin, [], ['title' => $title]);
    $o->forceFill(['status' => $status, 'completed_at' => $completedAt])->save();

    return $o;
}

it('mese predefinito, mesi non validi o futuri', function () {
    $now = CarbonImmutable::parse('2026-04-10 12:00');
    expect(MonthlyReport::monthFrom(null, $now)->toDateString())->toBe('2026-03-01')
        ->and(MonthlyReport::monthFrom('2026-01', $now)->toDateString())->toBe('2026-01-01')
        ->and(MonthlyReport::monthFrom('2026-04', $now)->toDateString())->toBe('2026-04-01');
    expect(fn () => MonthlyReport::monthFrom('2026-13', $now))->toThrow(InvalidArgumentException::class)
        ->and(fn () => MonthlyReport::monthFrom('2026-5', $now))->toThrow(InvalidArgumentException::class)
        ->and(fn () => MonthlyReport::monthFrom('2026-05', $now))->toThrow(InvalidArgumentException::class);
});

it('rapporto del mese: indicatori, ordini conclusi nel mese, ricambi sotto scorta, piani in ritardo', function () {
    $this->travelTo('2026-04-10 12:00:00');
    concludedOrder($this->admin, 'Sostituzione cinghia marzo', '2026-03-31 23:30:00');
    concludedOrder($this->admin, 'Revisione pompa marzo', '2026-03-01 00:00:00', 'completato');
    concludedOrder($this->admin, 'Intervento di aprile', '2026-04-01 00:00:00');
    concludedOrder($this->admin, 'Intervento di febbraio', '2026-02-28 23:59:00');
    $annullato = makeOrder($this->admin, [], ['title' => 'Annullato di marzo']);
    $annullato->forceFill(['status' => 'annullato', 'completed_at' => '2026-03-15 10:00'])->save();

    $main = makeWarehouse();
    $low = makePart(['code' => 'R-SOTTO', 'name' => 'Filtro aria', 'min_stock' => 5]);
    app(Stock::class)->receive($low, $main, 1, $this->admin, 3);
    makePart(['code' => 'R-OK', 'min_stock' => 0]);
    makePlan(['title' => 'Controllo freni', 'next_due_on' => '2026-03-20']);
    makePlan(['title' => 'Piano in regola', 'next_due_on' => '2026-05-20']);

    $page = $this->actingAs($this->admin)->get(route('kpi.report'));
    $page->assertOk()
        ->assertSee(__('kpi.report.title'))->assertSee('Marzo 2026')
        ->assertSee('Sostituzione cinghia marzo')->assertSee('Revisione pompa marzo')
        ->assertDontSee('Intervento di aprile')->assertDontSee('Intervento di febbraio')->assertDontSee('Annullato di marzo')
        ->assertSee(__('kpi.report.orders').' (2)')
        ->assertSee('R-SOTTO')->assertDontSee('R-OK')
        ->assertSee('Controllo freni')->assertDontSee('Piano in regola')
        ->assertSee(trans_choice('kpi.report.days_late', 21, ['n' => 21]))
        ->assertSee(__('kpi.insufficient'))
        ->assertSee(__('kpi.costs.title'))
        ->assertSee('@page', false);

    // Un altro mese, con la navigazione
    $this->actingAs($this->admin)->get(route('kpi.report', ['mese' => '2026-04']))->assertOk()->assertSee('Intervento di aprile')->assertDontSee('Sostituzione cinghia marzo');
    $this->actingAs($this->admin)->get(route('kpi.report', ['mese' => '2026-05']))->assertStatus(422);
    $this->actingAs($this->admin)->get(route('kpi.report', ['mese' => 'marzo']))->assertStatus(422);
});

it('ogni parte solo a chi la può vedere', function () {
    $this->travelTo('2026-04-10 12:00:00');
    concludedOrder($this->admin, 'Ordine di marzo', '2026-03-10 10:00');

    // Magazziniere: indicatori, ordini e magazzino; niente costi né piani
    $this->actingAs(userWithRole(Access::STOREKEEPER))->get(route('kpi.report'))->assertOk()
        ->assertSee('Ordine di marzo')->assertSee(__('kpi.report.low_stock'))
        ->assertDontSee(__('kpi.report.late_plans'))->assertDontSee(__('kpi.report.col.cost'))->assertDontSee('€');

    $this->actingAs(userWithRole(Access::VIEWER))->get(route('kpi.report'))->assertOk()->assertSee(__('kpi.report.late_plans'))->assertSee('€');
    $this->actingAs(userWithRole(Access::TECHNICIAN))->get(route('kpi.report'))->assertForbidden();
    $this->actingAs(userWithRole(Access::REQUESTER))->get(route('kpi.report'))->assertForbidden();
});

it('il 1° del mese avvisa chi vede gli indicatori', function () {
    $this->travelTo('2026-05-01 07:20:00');
    $manager = userWithRole(Access::MANAGER);
    $tech = userWithRole(Access::TECHNICIAN);

    $this->artisan('manutrack:rapporto-mensile')->assertSuccessful();

    $n = UserNotification::query()->where('user_id', $manager->id)->first();
    expect($n->event)->toBe(NotificationEvent::MonthlyReport)
        ->and($n->title)->toBe(__('kpi.report.notify_title', ['month' => 'Aprile 2026']))
        ->and($n->url)->toBe('/indicatori/rapporto?mese=2026-04')
        ->and(UserNotification::query()->where('user_id', $this->admin->id)->exists())->toBeTrue()
        ->and(UserNotification::query()->where('user_id', $tech->id)->exists())->toBeFalse();
});
