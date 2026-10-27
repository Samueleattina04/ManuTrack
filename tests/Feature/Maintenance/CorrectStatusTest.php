<?php

use App\Actions\Maintenance\CorrectWorkOrderStatus;
use App\Actions\Maintenance\SubmitWorkRequest;
use App\Actions\Maintenance\TransitionWorkOrder;
use App\Actions\Maintenance\TriageWorkRequest;
use App\Enums\WorkOrderStatus as S;
use App\Livewire\WorkOrders\Show;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\DowntimeEvent;
use App\Models\TimeEntry;
use App\Support\Access\Access;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->tech = userWithRole(Access::TECHNICIAN);
    $this->move = app(TransitionWorkOrder::class);
    $this->correct = app(CorrectWorkOrderStatus::class);
});

/** Ordine nato da una segnalazione con macchina ferma, completato e chiuso */
function closedOrderWithDowntime($test): array
{
    $asset = Asset::factory()->create();
    $test->travelTo(now()->subHours(5));
    $request = app(SubmitWorkRequest::class)->handle(userWithRole(Access::REQUESTER), ['asset_id' => $asset->id, 'description' => 'Ferma', 'priority' => 'alta', 'machine_stopped' => true])['request'];
    $order = app(TriageWorkRequest::class)->accept($request, $test->admin, ['assignees' => [$test->tech->id]]);
    $test->travelTo(now()->addHour());
    $test->move->handle($order, S::InProgress, $test->tech);
    $test->travelTo(now()->addHours(2));
    $test->move->handle($order->refresh(), S::Completed, $test->tech, ['completion_notes' => 'Fatto']);
    $test->move->handle($order->refresh(), S::Closed, $test->admin);
    $test->travelBack();

    return [$order->refresh(), $asset];
}

it('l\'amministratore riapre un ordine chiuso, con motivo nello storico e nel registro', function () {
    [$order] = closedOrderWithDowntime($this);

    Livewire::actingAs($this->admin)->test(Show::class, ['workOrder' => $order])
        ->call('startCorrection')->set('correction.to', 'in_corso')->set('correction.reason', 'Chiuso per errore, manca la prova')
        ->call('saveCorrection')->assertHasNoErrors();

    $order->refresh();
    $last = $order->statusChanges->last();
    expect($order->status)->toBe(S::InProgress)
        ->and($order->closed_at)->toBeNull()->and($order->completed_at)->toBeNull()->and($order->labor_rate)->toBeNull()
        ->and($last->is_correction)->toBeTrue()
        ->and($last->from)->toBe(S::Closed)
        ->and($last->note)->toBe('Chiuso per errore, manca la prova');

    $log = AuditLog::query()->where('action', 'work_orders.status_corrected')->sole();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->description)->toContain('Chiuso per errore')
        ->and($log->changes['status'])->toBe(['chiuso', 'in_corso']);

    // Da qui si riprende il ciclo normale
    $this->move->handle($order, S::Completed, $this->tech, ['completion_notes' => 'Ora sì']);
    expect($order->refresh()->status)->toBe(S::Completed);
});

it('il motivo è obbligatorio e lo stato deve cambiare', function () {
    [$order] = closedOrderWithDowntime($this);

    expect(fn () => $this->correct->handle($order, $this->admin, ['to' => 'in_corso', 'reason' => '']))->toThrow(ValidationException::class)
        ->and(fn () => $this->correct->handle($order, $this->admin, ['to' => 'chiuso', 'reason' => 'Nessun cambio']))->toThrow(ValidationException::class)
        ->and(fn () => $this->correct->handle($order, $this->admin, ['to' => 'in_attesa', 'reason' => 'Manca il motivo', 'waiting_reason' => null]))->toThrow(ValidationException::class);
});

it('nessun altro ruolo può correggere lo stato, nemmeno il responsabile', function (string $role) {
    $order = makeOrder($this->admin);
    $user = userWithRole($role);

    expect($user->can('correctStatus', $order))->toBeFalse();
    if ($user->can('view', $order)) {
        Livewire::actingAs($user)->test(Show::class, ['workOrder' => $order])->call('startCorrection')->assertForbidden();
        Livewire::actingAs($user)->test(Show::class, ['workOrder' => $order])->set('correction.to', 'chiuso')->set('correction.reason', 'Tentativo')->call('saveCorrection')->assertForbidden();
        $this->actingAs($user)->get(route('work-orders.show', $order))->assertDontSee(__('maintenance.correction.button'));
    }
    expect($order->refresh()->status)->toBe(S::Open);
})->with([Access::MANAGER, Access::TECHNICIAN, Access::STOREKEEPER, Access::VIEWER, Access::REQUESTER]);

it('spostando la data di completamento si sposta anche la fine del fermo', function () {
    [$order] = closedOrderWithDowntime($this);
    $downtime = DowntimeEvent::query()->sole();
    expect($downtime->ended_at->diffInSeconds($order->completed_at, true))->toBeLessThan(2);

    $earlier = $order->completed_at->copy()->subMinutes(90);
    $this->correct->handle($order, $this->admin, ['to' => 'chiuso', 'reason' => 'Ora di fine sbagliata', 'completed_at' => $earlier->format('Y-m-d H:i:s')]);

    expect($order->refresh()->completed_at->format('Y-m-d H:i'))->toBe($earlier->format('Y-m-d H:i'))
        ->and($downtime->refresh()->ended_at->format('Y-m-d H:i'))->toBe($earlier->format('Y-m-d H:i'))
        ->and(AuditLog::query()->where('action', 'work_orders.status_corrected')->sole()->changes['downtime'])->toHaveCount(1);
});

it('riaprendo si può riaprire il fermo; annullando i fermi aperti si chiudono', function () {
    [$order, $asset] = closedOrderWithDowntime($this);

    $this->correct->handle($order, $this->admin, ['to' => 'in_corso', 'reason' => 'La macchina è di nuovo ferma', 'downtime' => 'reopen']);
    expect(DowntimeEvent::query()->sole()->ended_at)->toBeNull();

    $this->correct->handle($order->refresh(), $this->admin, ['to' => 'annullato', 'reason' => 'Intervento rifatto in un altro ordine']);
    $downtime = DowntimeEvent::query()->sole();
    expect($downtime->ended_at)->not->toBeNull()
        ->and($order->refresh()->status)->toBe(S::Cancelled)
        ->and(TimeEntry::query()->running()->count())->toBe(0);
});

it('"lascia com\'è" non tocca i fermi e la data non può precedere l\'inizio del lavoro', function () {
    [$order] = closedOrderWithDowntime($this);
    $end = DowntimeEvent::query()->sole()->ended_at;

    $this->correct->handle($order, $this->admin, ['to' => 'completato', 'reason' => 'Non ancora chiudibile', 'downtime' => 'keep']);
    expect(DowntimeEvent::query()->sole()->ended_at->equalTo($end))->toBeTrue()
        ->and($order->refresh()->closed_at)->toBeNull();

    expect(fn () => $this->correct->handle($order->refresh(), $this->admin, ['to' => 'chiuso', 'reason' => 'Data sbagliata', 'completed_at' => $order->started_at->copy()->subDay()->toDateTimeString()]))
        ->toThrow(ValidationException::class);
});

it('un fermo della segnalazione non ancora legato all\'ordine viene chiuso e collegato', function () {
    [$order, $asset] = closedOrderWithDowntime($this);
    $request = $order->request;
    // Fermo aperto dalla segnalazione ma rimasto senza ordine (es. dati di una versione precedente)
    $orphan = DowntimeEvent::query()->create(['asset_id' => Asset::factory()->create()->id, 'work_request_id' => $request->id, 'started_at' => now()->subHour()]);

    $this->correct->handle($order, $this->admin, ['to' => 'annullato', 'reason' => 'Doppione di un altro ordine']);

    expect($orphan->refresh()->ended_at)->not->toBeNull()
        ->and($orphan->work_order_id)->toBe($order->id);
});
