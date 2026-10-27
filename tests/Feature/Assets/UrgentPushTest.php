<?php

use App\Jobs\SendPushMessage;
use App\Livewire\Assets\Show;
use App\Models\Asset;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessage;
use App\Support\Push\PushResult;
use App\Support\Push\PushSender;
use App\Support\Push\PushTransport;
use App\Support\Push\PushUrgency;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\RecordingPushTransport;

function subscribeDevice(User $user, string $suffix): PushSubscription
{
    $endpoint = 'https://fcm.googleapis.com/fcm/send/'.$suffix;

    return PushSubscription::query()->create([
        'user_id' => $user->id,
        'endpoint' => $endpoint,
        'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
        'public_key' => 'chiave',
        'auth_token' => 'segreto',
    ]);
}

beforeEach(function () {
    $this->admin = installManuTrack();
    config([
        'manutrack.webpush.public_key' => 'pubblica',
        'manutrack.webpush.private_key' => 'privata',
        // Coda vera su database, come in produzione: così si vede cosa passa dalla coda
        'queue.default' => 'database',
    ]);
    $this->transport = new RecordingPushTransport;
    $this->app->instance(PushTransport::class, $this->transport);

    $this->manager = userWithRole(Access::MANAGER);
    $this->technician = userWithRole(Access::TECHNICIAN);
    $this->managerDevice = subscribeDevice($this->manager, 'responsabile');
    $this->adminDevice = subscribeDevice($this->admin, 'amministratore');
    $this->technicianDevice = subscribeDevice($this->technician, 'tecnico');
});

it('il guasto di una macchina che ferma la linea si invia dopo la risposta, non in coda', function () {
    Bus::fake([SendPushMessage::class]);
    $asset = Asset::factory()->create(['criticality' => 'A']);

    Livewire::actingAs($this->technician)->test(Show::class, ['asset' => $asset])
        ->set('newStatus', 'guasto')->call('changeStatus')->assertHasNoErrors();

    Bus::assertDispatchedAfterResponse(SendPushMessage::class, fn (SendPushMessage $job) => $job->urgency === PushUrgency::Immediate && $job->inline);
    expect(Bus::dispatched(SendPushMessage::class))->toHaveCount(0);
});

it('la notifica urgente arriva ai responsabili ad alta priorità senza aspettare il cron', function () {
    $asset = Asset::factory()->create(['criticality' => 'A', 'code' => 'PRS-01']);

    // La chiamata Livewire termina come una richiesta vera: l'invio avviene alla fine della risposta
    Livewire::actingAs($this->technician)->test(Show::class, ['asset' => $asset])
        ->set('newStatus', 'guasto')->set('statusNote', 'perdita d\'olio')
        ->call('changeStatus')->assertHasNoErrors();

    expect($asset->fresh()->status->value)->toBe('guasto')
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and($this->transport->sent)->toHaveCount(1);

    $sent = $this->transport->sent[0];
    expect($sent['urgency'])->toBe('high')
        ->and($sent['ids'])->toEqualCanonicalizing([$this->managerDevice->id, $this->adminDevice->id])
        ->and($sent['payload']['title'])->toContain('PRS-01')->toContain(__('enums.asset_status.guasto'))
        ->and($sent['payload']['body'])->toContain($this->technician->name)->toContain('perdita d\'olio')
        ->and($sent['payload']['url'])->toBe('/macchine/'.$asset->id);
});

it('il guasto di una macchina non critica va in coda e parte al giro del cron', function () {
    $asset = Asset::factory()->create(['criticality' => 'C']);

    Livewire::actingAs($this->technician)->test(Show::class, ['asset' => $asset])
        ->set('newStatus', 'ferma')->call('changeStatus');
    $this->app->terminate();

    expect($this->transport->sent)->toBeEmpty()
        ->and(DB::table('jobs')->count())->toBe(1);

    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    expect($this->transport->sent)->toHaveCount(1)
        ->and($this->transport->sent[0]['urgency'])->toBe('normal')
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('il ritorno in funzione avvisa in coda, gli altri cambi di stato non avvisano', function () {
    $asset = Asset::factory()->create(['criticality' => 'A', 'status' => 'guasto']);

    Livewire::actingAs($this->manager)->test(Show::class, ['asset' => $asset])->set('newStatus', 'operativa')->call('changeStatus');
    $this->app->terminate();
    expect(DB::table('jobs')->count())->toBe(1)->and($this->transport->sent)->toBeEmpty();

    Livewire::actingAs($this->manager)->test(Show::class, ['asset' => $asset->fresh()])->set('newStatus', 'manutenzione')->call('changeStatus');
    expect(DB::table('jobs')->count())->toBe(1);
});

it('chi cambia lo stato e le persone disattivate non ricevono l\'avviso', function () {
    $this->admin->forceFill(['is_active' => false])->save();
    $asset = Asset::factory()->create(['criticality' => 'A']);

    Livewire::actingAs($this->manager)->test(Show::class, ['asset' => $asset])->set('newStatus', 'guasto')->call('changeStatus');
    $this->app->terminate();

    // L'unico altro destinatario (amministratore) è disattivato: nessun invio
    expect($this->transport->sent)->toBeEmpty();
});

it('se l\'invio immediato fallisce per un problema temporaneo, il dispositivo passa alla coda', function () {
    $this->transport->respond = fn (int $id) => $id === $this->managerDevice->id
        ? new PushResult($id, false, status: null, reason: 'timeout')
        : new PushResult($id, true, status: 201);

    app(PushDispatcher::class)->send([$this->manager, $this->admin], new PushMessage('Prova', 'Testo'), PushUrgency::Immediate);
    $this->app->terminate();

    expect($this->transport->sent)->toHaveCount(1)
        ->and(DB::table('jobs')->count())->toBe(1);

    $job = unserialize(json_decode((string) DB::table('jobs')->value('payload'), true)['data']['command']);
    expect($job)->toBeInstanceOf(SendPushMessage::class)
        ->and($job->inline)->toBeFalse()
        ->and($job->subscriptionIds)->toBe([$this->managerDevice->id]);

    // Al giro di cron riprova solo quel dispositivo
    $this->transport->respond = null;
    Artisan::call('queue:work', ['--stop-when-empty' => true]);
    expect($this->transport->sent)->toHaveCount(2)
        ->and($this->transport->sent[1]['ids'])->toBe([$this->managerDevice->id]);
});

it('senza chiavi VAPID non prepara nessun invio', function () {
    config(['manutrack.webpush.public_key' => null]);
    app(PushDispatcher::class)->send($this->manager, new PushMessage('Prova', 'Testo'), PushUrgency::Immediate);
    app(PushDispatcher::class)->send($this->manager, new PushMessage('Prova', 'Testo'));
    $this->app->terminate();

    expect($this->transport->sent)->toBeEmpty()->and(DB::table('jobs')->count())->toBe(0);
});

it('cambiare lo stato richiede il permesso', function () {
    $asset = Asset::factory()->create();

    Livewire::actingAs(userWithRole(Access::REQUESTER))->test(Show::class, ['asset' => $asset])
        ->set('newStatus', 'guasto')->call('changeStatus')->assertForbidden();
    Livewire::actingAs(userWithRole(Access::VIEWER))->test(Show::class, ['asset' => $asset])
        ->set('newStatus', 'guasto')->call('changeStatus')->assertForbidden();

    expect($asset->fresh()->status->value)->toBe('operativa');
});

it('dopo un invio immediato il limite di tempo del processo torna quello di prima', function () {
    set_time_limit(0);
    (new SendPushMessage([$this->manager->id], new PushMessage('Prova', 'corpo'), PushUrgency::Immediate, inline: true))->handle(app(PushSender::class));

    expect($this->transport->sent)->toHaveCount(1)
        ->and(ini_get('max_execution_time'))->toBe('0');
});
