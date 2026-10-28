<?php

use App\Livewire\Gdpr\DataExports;
use App\Livewire\Gdpr\Erase;
use App\Livewire\Profile;
use App\Livewire\Users\Index;
use App\Models\Asset;
use App\Models\DataExport;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Support\Access\Access;
use App\Support\Gdpr\Eraser;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

/*
 * Controllo di sicurezza 0.1: limitazione delle richieste. Per ogni limite: superato si
 * riceve 429 (o il messaggio previsto) e il limite vale per la chiave giusta.
 */

beforeEach(function () {
    $this->admin = installManuTrack();
    Notification::fake();
});

afterEach(function () {
    @unlink(Eraser::reportPath());
});

it('accesso: 5 tentativi al minuto per email e indirizzo, 30 per indirizzo', function () {
    $other = userWithRole(Access::TECHNICIAN, ['email' => 'altro@example.test']);
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'sbagliata'])->assertStatus(302);
    }
    $this->post('/login', ['email' => $this->admin->email, 'password' => 'Password1234'])->assertStatus(429);
    // Un'altra persona dallo stesso indirizzo non è bloccata dai tentativi sull'amministratore
    $this->post('/login', ['email' => $other->email, 'password' => 'sbagliata'])->assertStatus(302);
    // Da un altro indirizzo lo stesso account si prova ancora (il limite è per email e IP)
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->post('/login', ['email' => $this->admin->email, 'password' => 'sbagliata'])->assertStatus(302);

    // Molte email diverse dallo stesso indirizzo: al trentunesimo tentativo si ferma
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2']);
    foreach (range(1, 30) as $i) {
        $this->post('/login', ['email' => "nessuno{$i}@example.test", 'password' => 'sbagliata'])->assertStatus(302);
    }
    $this->post('/login', ['email' => 'nessuno99@example.test', 'password' => 'sbagliata'])->assertStatus(429);
});

it('accesso: un indirizzo inesistente costa quanto uno vero (niente enumerazione dai tempi)', function () {
    Hash::spy();
    $this->post('/login', ['email' => 'nessuno@example.test', 'password' => 'sbagliata']);
    Hash::shouldHaveReceived('check')->once();
});

it('verifica in due passaggi: 5 codici al minuto per persona', function () {
    app(EnableTwoFactorAuthentication::class)($this->admin);
    $secret = decrypt($this->admin->fresh()->two_factor_secret);
    app(ConfirmTwoFactorAuthentication::class)($this->admin->fresh(), (new Google2FA)->getCurrentOtp($secret));

    $this->post('/login', ['email' => $this->admin->email, 'password' => 'Password1234'])->assertRedirect(route('two-factor.login'));
    foreach (range(1, 5) as $i) {
        $this->post('/two-factor-challenge', ['code' => '000000'])->assertStatus(302);
    }
    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertStatus(429);
    $this->assertGuest();
});

it('inviti: 10 richieste al minuto per indirizzo IP sulla pagina dell\'invito', function () {
    foreach (range(1, 10) as $i) {
        expect($this->post('/invito/codice-'.$i, ['name' => 'Prova', 'password' => 'Password1234', 'password_confirmation' => 'Password1234'])->status())->not->toBe(429);
    }
    $this->post('/invito/codice-11', [])->assertStatus(429);
    $this->get('/invito/codice-12')->assertStatus(429);
    expect($this->withServerVariables(['REMOTE_ADDR' => '198.51.100.3'])->get('/invito/codice-13')->status())->not->toBe(429);
});

it('inviti inviati: 3 l\'ora per indirizzo email e 20 l\'ora per chi invita', function () {
    $this->actingAs($this->admin);
    $page = Livewire::test(Index::class);
    foreach (range(1, 3) as $i) {
        $page->set('inviteEmail', 'ripetuto@example.test')->set('inviteRole', Access::TECHNICIAN)->call('invite')->assertHasNoErrors();
    }
    $page->set('inviteEmail', 'ripetuto@example.test')->call('invite')->assertHasErrors('inviteEmail');
    $invitation = Invitation::query()->pending()->where('email', 'ripetuto@example.test')->sole();
    $page->call('resend', $invitation->id)->assertDispatched('toast', tone: 'ko');
    expect(Notification::sent($invitation, InvitationNotification::class)->count() + Invitation::query()->where('email', 'ripetuto@example.test')->whereNotNull('revoked_at')->count())->toBe(3);

    foreach (range(1, 17) as $i) {
        $page->set('inviteEmail', "persona{$i}@example.test")->call('invite')->assertHasNoErrors();
    }
    $page->set('inviteEmail', 'persona-ventunesima@example.test')->call('invite')->assertHasErrors('inviteEmail');
    expect(Invitation::query()->count())->toBe(20);

    // Il limite è di chi invita: un altro amministratore invita ancora
    $this->actingAs(userWithRole(Access::ADMIN));
    Livewire::test(Index::class)->set('inviteEmail', 'altro.invito@example.test')->set('inviteRole', Access::TECHNICIAN)->call('invite')->assertHasNoErrors();
});

it('password dimenticata: 5 richieste al minuto per indirizzo IP, stessa risposta per chi esiste e chi no', function () {
    $known = $this->post('/forgot-password', ['email' => $this->admin->email]);
    $unknown = $this->post('/forgot-password', ['email' => 'nessuno@example.test']);
    // Una seconda richiesta per lo stesso account entro un minuto non parte, ma la risposta non lo dice
    $again = $this->post('/forgot-password', ['email' => $this->admin->email]);
    foreach ([$known, $unknown, $again] as $response) {
        $response->assertRedirect()->assertSessionHas('status', __('passwords.sent'))->assertSessionHasNoErrors();
    }
    Notification::assertSentToTimes($this->admin, ResetPassword::class, 1);

    $this->post('/forgot-password', ['email' => 'a@example.test']);
    $this->post('/forgot-password', ['email' => 'b@example.test']);
    $this->post('/forgot-password', ['email' => 'c@example.test'])->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.4'])->post('/forgot-password', ['email' => 'd@example.test'])->assertStatus(302);
});

it('uso del link per la nuova password: 10 tentativi al minuto per indirizzo IP', function () {
    foreach (range(1, 10) as $i) {
        $this->post('/reset-password', ['token' => 'falso-'.$i, 'email' => $this->admin->email, 'password' => 'NuovaPassword99', 'password_confirmation' => 'NuovaPassword99'])->assertStatus(302);
    }
    $this->post('/reset-password', ['token' => 'falso', 'email' => $this->admin->email, 'password' => 'NuovaPassword99', 'password_confirmation' => 'NuovaPassword99'])->assertStatus(429);
    expect(Hash::check('Password1234', $this->admin->fresh()->password))->toBeTrue();
});

it('conferma della password: 5 tentativi al minuto per persona', function () {
    $this->actingAs($this->admin);
    foreach (range(1, 5) as $i) {
        $this->post('/user/confirm-password', ['password' => 'sbagliata'])->assertStatus(302);
    }
    $this->post('/user/confirm-password', ['password' => 'Password1234'])->assertStatus(429);
    expect(session('auth.password_confirmed_at'))->toBeNull();

    $this->actingAs(userWithRole(Access::ADMIN, ['password' => Hash::make('Password1234')]));
    $this->post('/user/confirm-password', ['password' => 'Password1234'])->assertStatus(302);
});

it('password attuale nel profilo e nella cancellazione totale: 5 verifiche al minuto', function () {
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
    $profile = Livewire::test(Profile::class);
    foreach (range(1, 5) as $i) {
        $profile->set('currentPassword', 'sbagliata')->set('password', 'NuovaPassword99')->set('password_confirmation', 'NuovaPassword99')->call('changePassword')->assertHasErrors('currentPassword');
    }
    // Il sesto tentativo è respinto anche con la password giusta
    $profile->set('currentPassword', 'Password1234')->call('changePassword')->assertHasErrors('currentPassword');
    $profile->set('twoFactorPassword', 'Password1234')->call('enableTwoFactor')->assertHasErrors('twoFactorPassword');
    Livewire::test(Erase::class)->set('understood', true)->set('phrase', 'CANCELLA OFFICINE PROVA')->set('password', 'Password1234')->call('erase')->assertHasErrors('password');

    expect(Hash::check('Password1234', $this->admin->fresh()->password))->toBeTrue()
        ->and($this->admin->fresh()->two_factor_secret)->toBeNull()
        ->and(Eraser::erased())->toBeFalse();
});

it('link per la nuova password mandato dall\'amministratore: uno al minuto per persona, 10 ogni 10 minuti', function () {
    $people = User::factory()->count(11)->create();
    $this->actingAs($this->admin);
    $page = Livewire::test(Index::class);

    $page->call('sendPasswordReset', $people[0]->id)->assertDispatched('toast', message: __('users.reset_link_sent', ['email' => $people[0]->email]));
    $page->call('sendPasswordReset', $people[0]->id)->assertDispatched('toast', message: __('passwords.throttled'), tone: 'ko');
    Notification::assertSentToTimes($people[0], ResetPassword::class, 1);

    foreach (range(1, 8) as $i) {
        $page->call('sendPasswordReset', $people[$i]->id);
    }
    $page->call('sendPasswordReset', $people[10]->id)->assertDispatched('toast', tone: 'ko');
    Notification::assertNothingSentTo($people[10]);
});

it('esportazioni CSV ed Excel e rapporto mensile: 10 al minuto per persona', function () {
    $this->actingAs($this->admin);
    foreach (range(1, 10) as $i) {
        $this->get(route('export.assets', 'csv'))->assertOk();
    }
    $this->get(route('export.work-orders', 'xlsx'))->assertStatus(429);
    $this->get(route('kpi.report'))->assertStatus(429);

    $this->actingAs(userWithRole(Access::MANAGER));
    $this->get(route('export.assets', 'csv'))->assertOk();
});

it('esportazione completa: una alla volta e al massimo 3 l\'ora', function () {
    Queue::fake();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
    $page = Livewire::test(DataExports::class);
    foreach (range(1, 3) as $i) {
        $page->call('start');
        DataExport::query()->update(['status' => 'pronto']);
    }
    $page->call('start')->assertDispatched('toast', tone: 'warn');
    expect(DataExport::query()->count())->toBe(3);
});

it('caricamenti temporanei di Livewire: 60 al minuto per persona', function () {
    Storage::fake('tmp-for-tests');
    $tech = userWithRole(Access::TECHNICIAN);
    $this->actingAs($tech);
    $url = fn () => (new GenerateSignedUploadUrl)->forLocal();
    foreach (range(1, 60) as $i) {
        $this->post($url(), ['files' => [UploadedFile::fake()->image('foto.jpg', 10, 10)]])->assertOk();
    }
    $this->post($url(), ['files' => [UploadedFile::fake()->image('foto.jpg', 10, 10)]])->assertStatus(429);

    $this->actingAs(userWithRole(Access::TECHNICIAN));
    $this->post($url(), ['files' => [UploadedFile::fake()->image('foto.jpg', 10, 10)]])->assertOk();
});

it('modelli 3D: 60 caricamenti al minuto per persona', function () {
    $asset = Asset::factory()->create();
    $this->actingAs($this->admin);
    foreach (range(1, 60) as $i) {
        $this->post(route('assets.model.upload', $asset), [])->assertStatus(302);
    }
    $this->post(route('assets.model.upload', $asset), [])->assertStatus(429);
});

it('segnalazioni: 30 al minuto per persona; la coda offline che supera il limite riprova dopo', function () {
    $tech = userWithRole(Access::TECHNICIAN);
    $this->actingAs($tech);
    $headers = ['X-ManuTrack' => '1', 'Accept' => 'application/json'];
    foreach (range(1, 30) as $i) {
        $this->post(route('requests.store'), reportPayload(), $headers)->assertSuccessful();
    }
    $this->post(route('requests.store'), reportPayload(), $headers)->assertStatus(429);
    $this->actingAs(userWithRole(Access::REQUESTER))->post(route('requests.store'), reportPayload(), $headers)->assertSuccessful();

    // Nella coda del telefono un 429 è un problema temporaneo: la segnalazione resta e riparte
    expect((string) file_get_contents(resource_path('js/requests/outbox.js')))->toContain('res.status === 429');
});
