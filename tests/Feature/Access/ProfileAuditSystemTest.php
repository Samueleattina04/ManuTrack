<?php

use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Profile;
use App\Models\AuditLog;
use App\Models\SystemVersion;
use App\Support\Access\Access;
use App\Support\AppVersion;
use App\Support\Audit\Audit;
use App\Support\Update\Updater;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->admin = installManuTrack();
});

it('aggiorna i propri dati e la password', function () {
    Livewire::actingAs($this->admin)->test(Profile::class)
        ->set('name', 'Nome Nuovo')->set('jobTitle', 'Responsabile tecnico')->call('saveProfile')->assertHasNoErrors();
    expect($this->admin->fresh()->name)->toBe('Nome Nuovo');

    Livewire::actingAs($this->admin)->test(Profile::class)
        ->set('currentPassword', 'sbagliata')->set('password', 'NuovaPassword99')->set('password_confirmation', 'NuovaPassword99')
        ->call('changePassword')->assertHasErrors('currentPassword');

    Livewire::actingAs($this->admin)->test(Profile::class)
        ->set('currentPassword', 'Password1234')->set('password', 'NuovaPassword99')->set('password_confirmation', 'NuovaPassword99')
        ->call('changePassword')->assertHasNoErrors();
    expect(Hash::check('NuovaPassword99', $this->admin->fresh()->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'profile.password_changed')->exists())->toBeTrue();
});

it('attiva la verifica in due passaggi dal profilo', function () {
    Livewire::actingAs($this->admin)->test(Profile::class)
        ->set('twoFactorPassword', 'Password1234')->call('enableTwoFactor')->assertHasNoErrors()
        ->assertSee(__('profile.two_factor.scan'));

    $secret = decrypt($this->admin->fresh()->two_factor_secret);
    Livewire::actingAs($this->admin->fresh())->test(Profile::class)
        ->set('twoFactorCode', (new Google2FA)->getCurrentOtp($secret))->call('confirmTwoFactor')
        ->assertSee(__('profile.two_factor.codes_title'));

    expect($this->admin->fresh()->hasTwoFactor())->toBeTrue();
});

it('il registro attività è visibile solo a chi ha il permesso', function () {
    $this->post('/login', ['email' => 'admin@example.test', 'password' => 'Password1234']);

    Livewire::actingAs($this->admin)->test(AuditIndex::class)->assertSee(__('audit.actions.auth.login'));
    Livewire::actingAs(userWithRole(Access::TECHNICIAN))->test(AuditIndex::class)->assertForbidden();
});

it('il registro non salva mai password o segreti', function () {
    Audit::log('prova', $this->admin, null, ['password' => ['a', 'b'], 'two_factor_secret' => 'x', 'name' => ['A', 'B']]);

    expect(AuditLog::query()->where('action', 'prova')->sole()->changes)->toBe(['name' => ['A', 'B']]);
});

it('dalla pagina Sistema l\'amministratore crea una copia e fa ritentare un aggiornamento', function () {
    $this->actingAs($this->admin);

    $this->post(route('system.backup'))->assertRedirect(route('password.confirm'));

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('system'))->post(route('system.backup'))->assertRedirect(route('system'))->assertSessionHas('status');
    expect(glob(config('manutrack.backups.path').'/*-manuale.sql.gz'))->toHaveCount(1);

    $this->withSession(['auth.password_confirmed_at' => time()])->post(route('system.retry'))->assertSessionHas('status');
    expect(settings(Updater::RETRY_SETTING))->toBe(AppVersion::code());
});

it('dalla pagina Sistema l\'amministratore applica un aggiornamento in attesa', function () {
    AppVersion::fake('9.8.0');

    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('system.update'))->assertSessionHas('status');

    expect(AppVersion::installed())->toBe('9.8.0')
        ->and(SystemVersion::query()->latest('id')->first()->trigger)->toBe('web');
});

it('chi non è amministratore non usa le azioni di sistema', function () {
    $this->actingAs(userWithRole(Access::MANAGER))->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('system.backup'))->assertForbidden();
});
