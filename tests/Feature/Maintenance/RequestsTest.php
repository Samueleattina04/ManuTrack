<?php

use App\Actions\Maintenance\TriageWorkRequest;
use App\Jobs\SendPushMessage;
use App\Livewire\Requests\Show;
use App\Models\Asset;
use App\Models\PushSubscription;
use App\Models\WorkRequest;
use App\Support\Access\Access;
use App\Support\Push\PushTransport;
use App\Support\Push\PushUrgency;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Support\RecordingPushTransport;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->operator = userWithRole(Access::REQUESTER);
    $this->manager = userWithRole(Access::MANAGER);
    config(['manutrack.webpush.public_key' => 'pubblica', 'manutrack.webpush.private_key' => 'privata', 'queue.default' => 'database']);
    $this->transport = new RecordingPushTransport;
    $this->app->instance(PushTransport::class, $this->transport);
    foreach ([$this->manager, $this->operator] as $u) {
        PushSubscription::query()->create(['user_id' => $u->id, 'endpoint' => 'https://fcm.googleapis.com/x/'.$u->id, 'endpoint_hash' => PushSubscription::hashEndpoint('https://fcm.googleapis.com/x/'.$u->id), 'public_key' => 'k', 'auth_token' => 'a']);
    }
});

function sendReport($test, $user, array $data, array $headers = ['X-ManuTrack' => '1'])
{
    return $test->actingAs($user)->post(route('requests.store'), $data, $headers + ['Accept' => 'application/json']);
}

it('il telefono invia la segnalazione con foto e riceve il numero', function () {
    $asset = Asset::factory()->create(['code' => 'NAS-01']);
    $response = sendReport($this, $this->operator, reportPayload([
        'asset_id' => $asset->id,
        'photos' => [UploadedFile::fake()->image('a.jpg', 3000, 2000), UploadedFile::fake()->createWithContent('b.heic', "\0\0\0\x18ftypheic\0\0\0\0mif1heic")],
    ]));

    $response->assertCreated()->assertJson(['ok' => true, 'number' => 'SEG-'.now()->year.'-00001', 'duplicate' => false]);
    expect($response->json('photo_errors'))->toHaveCount(1); // la HEIC è conservata con un avviso
    $request = WorkRequest::query()->sole();
    expect($request->user_id)->toBe($this->operator->id)
        ->and($request->location_id)->toBe($asset->location_id)
        ->and($request->attachments()->where('kind', 'foto')->sole()->mime)->toBe('image/webp')
        ->and($request->attachments()->where('kind', 'documento')->count())->toBe(1);
});

it('lo stesso invio ripetuto dalla coda offline non crea doppioni e conserva l\'ora di scrittura', function () {
    $payload = reportPayload(['reported_at' => now()->subHours(3)->toIso8601String()]);

    sendReport($this, $this->operator, $payload)->assertCreated();
    sendReport($this, $this->operator, $payload)->assertOk()->assertJson(['duplicate' => true]);

    expect(WorkRequest::query()->count())->toBe(1)
        ->and(WorkRequest::query()->sole()->reported_at->diffInMinutes(now()->subHours(3), true))->toBeLessThan(1);

    // Ore non plausibili: si usa l'ora d'arrivo
    sendReport($this, $this->operator, reportPayload(['reported_at' => now()->addDay()->toIso8601String()]))->assertCreated();
    expect(WorkRequest::query()->latest('id')->first()->reported_at->diffInMinutes(now(), true))->toBeLessThan(1);
});

it('l\'invio è protetto: intestazione, stesso sito, persona giusta, dati validi', function () {
    sendReport($this, $this->operator, reportPayload(), [])->assertStatus(400);
    sendReport($this, $this->operator, reportPayload(), ['X-ManuTrack' => '1', 'Origin' => 'https://sito-estraneo.example'])->assertForbidden();
    sendReport($this, $this->operator, reportPayload(['user_id' => $this->manager->id]))->assertStatus(409);
    sendReport($this, $this->operator, reportPayload(['description' => '']))->assertStatus(422)->assertJsonValidationErrors('description');
    sendReport($this, $this->operator, reportPayload(['priority' => 'altissima']))->assertStatus(422);

    // Un codice già usato da un'altra persona
    $payload = reportPayload();
    sendReport($this, $this->manager, $payload)->assertCreated();
    sendReport($this, $this->operator, $payload)->assertStatus(409);

    auth()->logout();
    $this->post(route('requests.store'), reportPayload(), ['X-ManuTrack' => '1', 'Accept' => 'application/json'])->assertUnauthorized();
    expect(WorkRequest::query()->count())->toBe(1);
});

it('una macchina eliminata mentre la segnalazione era in coda non la blocca', function () {
    sendReport($this, $this->operator, reportPayload(['asset_id' => 999999]))->assertCreated();
    expect(WorkRequest::query()->sole()->asset_id)->toBeNull();
});

it('una segnalazione critica avvisa subito i responsabili, dopo la risposta', function () {
    Bus::fake([SendPushMessage::class]);
    sendReport($this, $this->operator, reportPayload(['priority' => 'critica']))->assertCreated();

    Bus::assertDispatchedAfterResponse(SendPushMessage::class, fn ($job) => $job->urgency === PushUrgency::Immediate
        && in_array($this->manager->id, $job->userIds, true) && ! in_array($this->operator->id, $job->userIds, true));
    expect(Bus::dispatched(SendPushMessage::class))->toHaveCount(0);
});

it('macchina A ferma = urgente; il telefono del responsabile riceve subito la notifica', function () {
    $asset = Asset::factory()->create(['criticality' => 'A', 'code' => 'PRS-01']);
    sendReport($this, $this->operator, reportPayload(['asset_id' => $asset->id, 'machine_stopped' => '1', 'priority' => 'alta']))->assertCreated();

    expect(DB::table('jobs')->count())->toBe(0)->and($this->transport->sent)->toHaveCount(1);
    $sent = $this->transport->sent[0];
    expect($sent['urgency'])->toBe('high')
        ->and($sent['payload']['title'])->toContain('PRS-01')->toContain('urgente')
        ->and($sent['payload']['body'])->toContain(__('maintenance.push.machine_stopped'))
        ->and($asset->fresh()->status->value)->toBe('guasto');
});

it('una segnalazione normale va in coda', function () {
    sendReport($this, $this->operator, reportPayload(['priority' => 'bassa']))->assertCreated();
    expect($this->transport->sent)->toBeEmpty()->and(DB::table('jobs')->count())->toBe(1);
});

it('accettare crea l\'ordine e avvisa chi ha segnalato e chi è assegnato', function () {
    $tech = userWithRole(Access::TECHNICIAN);
    PushSubscription::query()->create(['user_id' => $tech->id, 'endpoint' => 'https://x/t', 'endpoint_hash' => PushSubscription::hashEndpoint('https://x/t'), 'public_key' => 'k', 'auth_token' => 'a']);
    $request = makeRequest($this->operator, ['priority' => 'critica']);
    DB::table('jobs')->delete();

    Livewire::actingAs($this->manager)->test(Show::class, ['workRequest' => $request])
        ->call('start', 'accept')->set('priority', 'critica')->set('assignees', [(string) $tech->id])
        ->call('accept')->assertRedirect();

    $request->refresh();
    $order = $request->workOrder;
    expect($request->status->value)->toBe('accettata')
        ->and($order->assignees->pluck('id')->all())->toBe([$tech->id])
        ->and($order->priority->value)->toBe('critica')
        ->and($order->status->value)->toBe('pianificato')
        ->and($order->description)->toBe($request->description);

    // Ordine critico al tecnico: subito. Esito al richiedente: in coda.
    expect(collect($this->transport->sent)->pluck('ids')->flatten()->all())->toContain(PushSubscription::query()->where('user_id', $tech->id)->value('id'))
        ->and(DB::table('jobs')->count())->toBe(1);
});

it('respingere chiede il motivo; una segnalazione già valutata non si rivaluta', function () {
    $request = makeRequest($this->operator);

    Livewire::actingAs($this->manager)->test(Show::class, ['workRequest' => $request])->call('start', 'reject')->set('note', '')->call('reject')->assertHasErrors('note');
    Livewire::actingAs($this->manager)->test(Show::class, ['workRequest' => $request])->call('start', 'reject')->set('note', 'Funzionamento normale')->call('reject')->assertHasNoErrors();
    expect($request->fresh()->status->value)->toBe('respinta')->and($request->fresh()->triage_note)->toBe('Funzionamento normale');

    expect(fn () => app(TriageWorkRequest::class)->accept($request->fresh(), $this->admin))->toThrow(ValidationException::class);
});

it('una duplicata rimanda all\'originale e al suo ordine', function () {
    $original = makeRequest($this->operator);
    $order = app(TriageWorkRequest::class)->accept($original, $this->manager);
    $copy = makeRequest($this->operator);

    Livewire::actingAs($this->manager)->test(Show::class, ['workRequest' => $copy])->call('start', 'duplicate')->set('original', (string) $original->id)->call('markDuplicate')->assertHasNoErrors();

    expect($copy->fresh()->status->value)->toBe('duplicata')
        ->and($copy->fresh()->duplicate_of_id)->toBe($original->id)
        ->and($copy->fresh()->work_order_id)->toBe($order->id);
});

it('il QR porta chi può solo segnalare direttamente alla segnalazione di quella macchina', function () {
    $asset = Asset::factory()->create();
    $role = Role::findByName(Access::REQUESTER);
    $role->revokePermissionTo('assets.view');

    $this->actingAs($this->operator->fresh())->get(route('assets.qr', $asset->qr_token))
        ->assertRedirect(route('requests.create', ['macchina' => $asset->qr_token]));
    $this->actingAs($this->operator->fresh())->get(route('requests.create', ['macchina' => $asset->qr_token]))
        ->assertOk()->assertViewHas('config', fn (array $c) => $c['preselect'] === $asset->id);
});

it('all\'uscita si svuotano le pagine salvate sul telefono', function () {
    $this->actingAs($this->operator)->post(route('logout'))->assertHeader('Clear-Site-Data', '"cache"');
});
