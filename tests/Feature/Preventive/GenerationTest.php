<?php

use App\Actions\Assets\RecordMeterReading;
use App\Actions\Maintenance\CorrectWorkOrderStatus;
use App\Actions\Maintenance\TransitionWorkOrder;
use App\Actions\Preventive\GeneratePlanOrders;
use App\Enums\WorkOrderStatus as S;
use App\Jobs\SendPushMessage;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\ChecklistTemplate;
use App\Models\WorkOrder;
use App\Support\Access\Access;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->tech = userWithRole(Access::TECHNICIAN);
    $this->generator = app(GeneratePlanOrders::class);
    $this->move = app(TransitionWorkOrder::class);
});

/** Porta a termine l'ordine come farebbe il tecnico */
function finishOrder($test, WorkOrder $order): WorkOrder
{
    $order->checklist()->update(['done_at' => now(), 'done_by' => $test->tech->id, 'value' => '1']);
    $test->move->handle($order->refresh(), S::InProgress, $test->tech);

    return $test->move->handle($order->refresh(), S::Completed, $test->tech, ['completion_notes' => 'Fatto']);
}

it('genera l\'ordine preventivo quando entra nell\'anticipo, una volta sola', function () {
    $template = ChecklistTemplate::query()->create(['name' => 'Controllo mensile']);
    $template->items()->create(['label' => 'Livello olio', 'kind' => 'spunta', 'required' => true, 'position' => 1]);
    $plan = makePlan(['next_due_on' => today()->addDays(10)->toDateString(), 'lead_days' => 7, 'assignee_id' => $this->tech->id, 'checklist_template_id' => $template->id, 'priority' => 'alta']);

    expect($this->generator->run())->toBe([]);

    $this->travel(3)->days();
    $results = $this->generator->run();
    expect($results)->toHaveCount(1);

    $order = $results[0]['order'];
    expect($order->type->value)->toBe('preventivo')
        ->and($order->maintenance_plan_id)->toBe($plan->id)
        ->and($order->plan_due_on->toDateString())->toBe($plan->next_due_on->toDateString())
        ->and($order->due_at->format('Y-m-d H:i'))->toBe($plan->next_due_on->toDateString().' 23:59')
        ->and($order->priority->value)->toBe('alta')
        ->and($order->status)->toBe(S::Planned)
        ->and($order->assignees->pluck('id')->all())->toBe([$this->tech->id])
        ->and($order->checklist)->toHaveCount(1)
        ->and($order->created_by)->toBeNull()
        ->and($plan->fresh()->current_work_order_id)->toBe($order->id)
        ->and(AuditLog::query()->where('action', 'preventive.generated')->exists())->toBeTrue();

    // Nessun doppione finché l'ordine è aperto, anche con la scadenza passata
    $this->travel(20)->days();
    expect($this->generator->run())->toBe([])->and(WorkOrder::query()->count())->toBe(1);
});

it('al completamento passa alla scadenza successiva e genera di nuovo solo nell\'anticipo', function () {
    $this->travelTo('2026-03-01 08:00');
    $plan = makePlan(['next_due_on' => '2026-03-05', 'start_on' => '2026-03-05', 'interval_value' => 1, 'interval_unit' => 'mesi', 'assignee_id' => $this->tech->id]);
    $order = $this->generator->run()[0]['order'];

    $this->travelTo('2026-03-04 10:00');
    finishOrder($this, $order);

    $plan->refresh();
    expect($plan->next_due_on->toDateString())->toBe('2026-04-05')
        ->and($plan->current_work_order_id)->toBeNull()
        ->and($plan->last_done_at->toDateString())->toBe('2026-03-04');

    $this->travelTo('2026-03-28 08:00');
    expect($this->generator->run())->toBe([]);
    $this->travelTo('2026-03-29 08:00');
    expect($this->generator->run())->toHaveCount(1);
});

it('un ordine annullato conta come saltato e il piano va avanti', function () {
    $this->travelTo('2026-03-01 08:00');
    $plan = makePlan(['next_due_on' => '2026-03-05', 'start_on' => '2026-03-05']);
    $order = $this->generator->run()[0]['order'];

    $this->move->handle($order, S::Cancelled, $this->admin, ['note' => 'Macchina ferma per ristrutturazione']);

    $plan->refresh();
    expect($plan->next_due_on->toDateString())->toBe('2026-04-05')
        ->and($plan->current_work_order_id)->toBeNull()
        ->and($plan->last_done_at)->toBeNull();
});

it('riaprire o chiudere un ordine già completato non fa avanzare il piano due volte', function () {
    $this->travelTo('2026-03-01 08:00');
    $plan = makePlan(['next_due_on' => '2026-03-05', 'start_on' => '2026-03-05', 'assignee_id' => $this->tech->id]);
    $order = finishOrder($this, $this->generator->run()[0]['order']);
    expect($plan->fresh()->next_due_on->toDateString())->toBe('2026-04-05');

    $this->move->handle($order, S::InProgress, $this->admin, ['note' => 'Ricontrollare']);
    $this->move->handle($order->refresh(), S::Completed, $this->tech, ['completion_notes' => 'Ricontrollato']);
    $this->move->handle($order->refresh(), S::Closed, $this->admin);
    expect($plan->fresh()->next_due_on->toDateString())->toBe('2026-04-05');
});

it('"Correggi stato" dell\'amministratore su un ordine del piano lo fa avanzare', function () {
    $this->travelTo('2026-03-01 08:00');
    $plan = makePlan(['next_due_on' => '2026-03-05', 'start_on' => '2026-03-05']);
    $order = $this->generator->run()[0]['order'];

    app(CorrectWorkOrderStatus::class)->handle($order, $this->admin, ['to' => S::Cancelled->value, 'reason' => 'Annullato per errore di pianificazione']);
    expect($plan->fresh()->current_work_order_id)->toBeNull()->and($plan->fresh()->next_due_on->toDateString())->toBe('2026-04-05');
});

it('a contatore: la lettura che supera la soglia crea subito l\'ordine', function () {
    $asset = Asset::factory()->create();
    $meter = $asset->meters()->create(['name' => 'Ore di lavoro', 'unit' => 'h']);
    $read = app(RecordMeterReading::class);
    $read->handle($meter, 4160, $this->admin, now()->subDays(30));
    $plan = makePlan(['asset_id' => $asset->id, 'trigger' => 'contatore', 'interval_value' => null, 'interval_unit' => null, 'meter_id' => $meter->id, 'meter_interval' => 500, 'meter_lead' => 50, 'next_meter_due' => 4500, 'next_due_on' => null, 'start_on' => null]);

    $read->handle($meter, 4360, $this->admin, now()->subDays(10));
    expect(WorkOrder::query()->count())->toBe(0);

    $read->handle($meter, 4460, $this->admin);
    $order = WorkOrder::query()->sole();
    expect($order->maintenance_plan_id)->toBe($plan->id)
        // 10 h al giorno: mancano 40 h → 4 giorni
        ->and($order->plan_due_on->toDateString())->toBe(today()->addDays(4)->toDateString())
        ->and($order->description)->toContain('soglia 4.500 h');

    $read->handle($meter, 4530, $this->admin);
    finishOrder($this, $order);
    expect((float) $plan->fresh()->next_meter_due)->toBe(5000.0);
});

it('azzerando il contatore la soglia mantiene quanto mancava', function () {
    $asset = Asset::factory()->create();
    $meter = $asset->meters()->create(['name' => 'Cicli', 'unit' => 'cicli']);
    $read = app(RecordMeterReading::class);
    $read->handle($meter, 4800, $this->admin);
    $plan = makePlan(['asset_id' => $asset->id, 'trigger' => 'contatore', 'interval_value' => null, 'interval_unit' => null, 'meter_id' => $meter->id, 'meter_interval' => 1000, 'next_meter_due' => 5000, 'next_due_on' => null, 'start_on' => null]);

    $read->handle($meter, 0, $this->admin, reset: true);
    expect((float) $plan->fresh()->next_meter_due)->toBe(200.0);
});

it('senza assegnatario avvisa chi pianifica; i piani sospesi non generano', function () {
    config(['manutrack.webpush.public_key' => 'pubblica', 'manutrack.webpush.private_key' => 'privata']);
    Bus::fake([SendPushMessage::class]);
    $manager = userWithRole(Access::MANAGER);
    makePlan(['next_due_on' => today()->toDateString()]);
    makePlan(['next_due_on' => today()->toDateString(), 'is_active' => false]);

    expect($this->generator->run())->toHaveCount(1);
    Bus::assertDispatched(SendPushMessage::class, fn ($job) => in_array($manager->id, $job->userIds, true) && ! in_array($this->tech->id, $job->userIds, true));
});

it('il comando con --prova non crea nulla', function () {
    makePlan(['next_due_on' => today()->toDateString()]);

    $this->artisan('manutrack:preventiva', ['--prova' => true])->assertSuccessful();
    expect(WorkOrder::query()->count())->toBe(0);
    $this->artisan('manutrack:preventiva')->assertSuccessful();
    expect(WorkOrder::query()->count())->toBe(1);
});

it('due esecuzioni del cron contemporanee creano un solo ordine', function () {
    $plan = makePlan(['next_due_on' => today()->toDateString()]);

    $results = raceWorkers('plan-worker.php', 5, [$plan->id]);

    expect(array_count_values($results))->toEqualCanonicalizing(['creato' => 1, 'nessuno' => 4])
        ->and(WorkOrder::query()->where('maintenance_plan_id', $plan->id)->count())->toBe(1);
})->skip(fn () => ! function_exists('proc_open'), 'proc_open non disponibile');
