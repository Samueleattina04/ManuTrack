<?php

use App\Actions\Maintenance\SubmitWorkRequest;
use App\Actions\Maintenance\TransitionWorkOrder;
use App\Actions\Maintenance\TriageWorkRequest;
use App\Actions\Maintenance\WorkTimer;
use App\Enums\WorkOrderStatus as S;
use App\Livewire\Maintenance\Settings;
use App\Livewire\WorkOrders\Show;
use App\Models\Asset;
use App\Models\ChecklistTemplate;
use App\Models\DowntimeEvent;
use App\Models\FailureCode;
use App\Models\Sequence;
use App\Models\TimeEntry;
use App\Support\Access\Access;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->tech = userWithRole(Access::TECHNICIAN);
    $this->move = app(TransitionWorkOrder::class);
});

it('numera ordini e segnalazioni per anno senza buchi', function () {
    $a = makeOrder($this->admin);
    $b = makeOrder($this->admin);
    expect($a->number)->toBe('OdL-'.now()->year.'-00001')->and($b->number)->toBe('OdL-'.now()->year.'-00002')
        ->and(makeRequest($this->tech)->number)->toBe('SEG-'.now()->year.'-00001')
        ->and(Sequence::next('OdL', now()->year + 1))->toBe(1);
});

it('segue il ciclo di vita completo con lo storico degli stati', function () {
    $order = makeOrder($this->admin, [$this->tech->id]);
    expect($order->status)->toBe(S::Planned);

    $this->move->handle($order, S::InProgress, $this->tech);
    $this->move->handle($order->refresh(), S::Waiting, $this->tech, ['waiting_reason' => 'ricambi']);
    expect($order->refresh()->waiting_reason->value)->toBe('ricambi');
    $this->move->handle($order, S::InProgress, $this->tech);
    $this->move->handle($order->refresh(), S::Completed, $this->tech, ['completion_notes' => 'Guarnizione sostituita']);
    $this->move->handle($order->refresh(), S::InProgress, $this->admin, ['note' => 'Perde ancora']);
    expect($order->refresh()->reopened_count)->toBe(1)->and($order->completed_at)->toBeNull();
    $this->move->handle($order, S::Completed, $this->tech, ['completion_notes' => 'Serrato il raccordo']);
    settings()->set('costs.hourly_rate', 40);
    $this->move->handle($order->refresh(), S::Closed, $this->admin);

    $order->refresh();
    expect($order->status)->toBe(S::Closed)
        ->and((float) $order->labor_rate)->toBe(40.0)
        ->and($order->statusChanges->map(fn ($c) => $c->to->value)->all())
        ->toBe(['aperto', 'pianificato', 'in_corso', 'in_attesa', 'in_corso', 'completato', 'in_corso', 'completato', 'chiuso']);
});

it('rifiuta i passaggi non previsti e i dati mancanti', function () {
    $order = makeOrder($this->admin);

    expect(fn () => $this->move->handle($order, S::Completed, $this->admin))->toThrow(ValidationException::class)
        ->and(fn () => $this->move->handle($order, S::Closed, $this->admin))->toThrow(ValidationException::class)
        ->and(fn () => $this->move->handle($order, S::Cancelled, $this->admin))->toThrow(ValidationException::class); // manca il motivo

    $this->move->handle($order, S::InProgress, $this->admin);
    expect(fn () => $this->move->handle($order->refresh(), S::Waiting, $this->admin, ['waiting_reason' => 'boh']))->toThrow(ValidationException::class)
        ->and(fn () => $this->move->handle($order->refresh(), S::Completed, $this->admin, ['completion_notes' => '']))->toThrow(ValidationException::class)
        ->and(fn () => $this->move->handle($order->refresh(), S::Cancelled, $this->admin, ['note' => 'non serve']))->toThrow(ValidationException::class);
});

it('non si completa con voci obbligatorie della checklist da fare', function () {
    $template = ChecklistTemplate::query()->create(['name' => 'Controllo']);
    $template->items()->createMany([
        ['position' => 1, 'label' => 'Protezioni montate', 'kind' => 'spunta', 'required' => true],
        ['position' => 2, 'label' => 'Pressione', 'kind' => 'valore', 'unit' => 'bar', 'required' => true],
        ['position' => 3, 'label' => 'Foto', 'kind' => 'spunta', 'required' => false],
    ]);
    $order = makeOrder($this->admin, [$this->tech->id], ['checklist_template_id' => $template->id]);
    $this->move->handle($order, S::InProgress, $this->tech);
    [$guard, $pressure] = $order->checklist()->get()->all();

    $page = Livewire::actingAs($this->tech)->test(Show::class, ['workOrder' => $order->refresh()]);
    $page->set('transition.completion_notes', 'Fatto')->call('move', 'completato')->call('move', 'completato')->assertHasErrors('transition.checklist');

    $page->call('toggleItem', $guard->id);
    $page->call('saveValue', $pressure->id)->assertHasErrors("values.{$pressure->id}");
    $page->set("values.{$pressure->id}", '180')->call('saveValue', $pressure->id);
    $page->call('move', 'completato')->assertHasNoErrors();

    expect($order->refresh()->status)->toBe(S::Completed)
        ->and($pressure->fresh()->value)->toBe('180')
        ->and($guard->fresh()->done_by)->toBe($this->tech->id);
});

it('cronometro: uno solo acceso per persona, si ferma in attesa e al completamento', function () {
    $timer = app(WorkTimer::class);
    $a = makeOrder($this->admin, [$this->tech->id]);
    $b = makeOrder($this->admin, [$this->tech->id]);

    $this->move->handle($a, S::InProgress, $this->tech); // parte il cronometro di chi inizia
    expect(TimeEntry::query()->running()->where('user_id', $this->tech->id)->count())->toBe(1);

    $this->move->handle($b, S::InProgress, $this->tech);
    expect(TimeEntry::query()->running()->where('user_id', $this->tech->id)->sole()->work_order_id)->toBe($b->id);

    $timer->start($a->refresh(), $this->tech);
    $this->travel(30)->minutes();
    $this->move->handle($a->refresh(), S::Waiting, $this->tech, ['waiting_reason' => 'fornitore']);
    expect(TimeEntry::query()->running()->count())->toBe(0)
        ->and($a->timeEntries()->latest('id')->first()->minutes)->toBe(30);

    $timer->add($b->refresh(), $this->tech, ['minutes' => 45, 'date' => now()->subDay()->toDateString()]);
    expect($b->refresh()->laborMinutes())->toBe(45)
        ->and(fn () => $timer->add($b, $this->tech, ['minutes' => 0, 'date' => now()->toDateString()]))->toThrow(ValidationException::class);
});

it('un guasto con macchina ferma apre il fermo; completando, la macchina torna operativa e il fermo si chiude', function () {
    $asset = Asset::factory()->create(['criticality' => 'B']);
    $operator = userWithRole(Access::REQUESTER);
    $this->travel(-2)->hours();
    $request = app(SubmitWorkRequest::class)->handle($operator, ['asset_id' => $asset->id, 'description' => 'Ferma', 'priority' => 'alta', 'machine_stopped' => true])['request'];
    $this->travelBack();

    expect($asset->fresh()->status->value)->toBe('guasto')
        ->and(DowntimeEvent::query()->open()->sole()->work_request_id)->toBe($request->id);

    $order = app(TriageWorkRequest::class)->accept($request, $this->admin, ['assignees' => [$this->tech->id]]);
    $this->move->handle($order, S::InProgress, $this->tech);
    expect($asset->fresh()->status->value)->toBe('manutenzione');

    $problem = FailureCode::query()->create(['kind' => 'problema', 'code' => 'P1', 'label' => 'Perdita']);
    $this->move->handle($order->refresh(), S::Completed, $this->tech, ['completion_notes' => 'Riparata', 'failure_problem_id' => $problem->id]);

    $downtime = DowntimeEvent::query()->sole();
    expect($asset->fresh()->status->value)->toBe('operativa')
        ->and($downtime->ended_at)->not->toBeNull()
        ->and($downtime->work_order_id)->toBe($order->id)
        ->and($downtime->minutes())->toBeGreaterThanOrEqual(119)
        ->and($order->refresh()->failure_problem_id)->toBe($problem->id);
});

it('completando si può lasciare la macchina com\'è', function () {
    $asset = Asset::factory()->create(['status' => 'ferma']);
    $order = makeOrder($this->admin, [$this->tech->id], ['asset_id' => $asset->id]);
    $this->move->handle($order, S::InProgress, $this->tech);
    $this->move->handle($order->refresh(), S::Completed, $this->tech, ['completion_notes' => 'Provvisorio', 'asset_operational' => false]);
    expect($asset->fresh()->status->value)->toBe('manutenzione');
});

it('i costi sommano manodopera e costi esterni', function () {
    settings()->set('costs.hourly_rate', 36);
    $order = makeOrder($this->admin, [$this->tech->id]);
    app(WorkTimer::class)->add($order, $this->tech, ['minutes' => 90, 'date' => now()->toDateString()]);

    Livewire::actingAs($this->admin)->test(Show::class, ['workOrder' => $order])->set('externalCost', '1.200,50')->call('saveExternalCost');

    expect($order->refresh()->costs())->toBe(['labor' => 54.0, 'external' => 1200.5, 'total' => 1254.5]);
});

it('dalle impostazioni si creano modelli di checklist, codici guasto e costo orario', function () {
    expect(Settings::parseItems("Pulire\n? Foto finale\nPressione [bar]\n\n"))->toBe([
        ['label' => 'Pulire', 'kind' => 'spunta', 'unit' => null, 'required' => true],
        ['label' => 'Foto finale', 'kind' => 'spunta', 'unit' => null, 'required' => false],
        ['label' => 'Pressione', 'kind' => 'valore', 'unit' => 'bar', 'required' => true],
    ]);

    Livewire::actingAs($this->admin)->test(Settings::class)
        ->call('editTemplate')->set('templateName', 'Dopo intervento')->set('templateItems', "Pulire\nPressione [bar]")->call('saveTemplate')->assertHasNoErrors()
        ->set('code.kind', 'causa')->set('code.code', 'c9')->set('code.label', 'Usura')->call('addCode')->assertHasNoErrors()
        ->set('code.kind', 'causa')->set('code.code', 'C9')->set('code.label', 'Doppione')->call('addCode')->assertHasErrors('code.code')
        ->set('hourlyRate', '42,50')->call('saveRate');

    expect(ChecklistTemplate::query()->sole()->items()->count())->toBe(2)
        ->and(FailureCode::query()->sole()->code)->toBe('C9')
        ->and((float) settings('costs.hourly_rate'))->toBe(42.5);
});

it('commenti e foto sull\'ordine', function () {
    $order = makeOrder($this->admin, [$this->tech->id]);
    $this->move->handle($order, S::InProgress, $this->tech);

    Livewire::actingAs($this->tech)->test(Show::class, ['workOrder' => $order->refresh()])
        ->set('comment', 'Serve una seconda persona')->call('addComment')
        ->set('photos', [UploadedFile::fake()->image('riparazione.jpg', 2400, 1800)]);

    expect($order->comments()->sole()->body)->toBe('Serve una seconda persona')
        ->and($order->attachments()->sole()->mime)->toBe('image/webp');
    $this->actingAs(userWithRole(Access::REQUESTER))->get($order->attachments()->sole()->url())->assertForbidden();
    $this->actingAs(userWithRole(Access::VIEWER))->get($order->attachments()->sole()->url())->assertOk();
});
