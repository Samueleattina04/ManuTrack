<?php

use App\Livewire\Profile;
use App\Livewire\Settings\Company;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\Security\AdminTwoFactor;
use Livewire\Livewire;

/*
 * Verifica in due passaggi obbligatoria per gli amministratori (impostazione dell'azienda).
 */

beforeEach(function () {
    $this->admin = installManuTrack();
});

function withTwoFactor(User $user): User
{
    $user->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['a-b'])), 'two_factor_confirmed_at' => now()])->save();

    return $user;
}

it('si attiva solo se chi la attiva ha già la verifica in due passaggi, e lo registra', function () {
    $this->actingAs($this->admin);
    Livewire::test(Company::class)->set('adminTwoFactor', true)->call('saveTwoFactor')
        ->assertHasErrors('adminTwoFactor')->assertSee(__('settings.two_factor.without', ['names' => $this->admin->name]));
    expect(AdminTwoFactor::required())->toBeFalse();

    withTwoFactor($this->admin);
    Livewire::test(Company::class)->set('adminTwoFactor', true)->call('saveTwoFactor')->assertHasNoErrors();
    expect(AdminTwoFactor::required())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'settings.admin_two_factor')->exists())->toBeTrue();

    // Chi gestisce le impostazioni senza essere amministratore non la vede e non la cambia
    $manager = userWithRole(Access::MANAGER);
    $manager->givePermissionTo('settings.manage');
    Livewire::actingAs($manager)->test(Company::class)->assertDontSee(__('settings.two_factor.title'))
        ->set('adminTwoFactor', false)->call('saveTwoFactor')->assertForbidden();
    expect(AdminTwoFactor::required())->toBeTrue();
});

it('con l\'obbligo, un amministratore senza verifica può solo andare al profilo ad attivarla', function () {
    withTwoFactor($this->admin);
    settings()->set(AdminTwoFactor::SETTING, true);
    $other = userWithRole(Access::ADMIN);
    $tech = userWithRole(Access::TECHNICIAN);

    // Pagina già aperta prima dell'obbligo: le sue azioni vengono bloccate (richiesta come dal browser)
    settings()->set(AdminTwoFactor::SETTING, false);
    $snapshot = livewireSnapshot($this->actingAs($other)->withSession(['auth.password_confirmed_at' => time()])->get(route('users.index'))->assertOk(), 'users');
    settings()->set(AdminTwoFactor::SETTING, true);
    livewireCall($this, $snapshot, [['sendPasswordReset', [$tech->id]]])->assertForbidden();

    $this->actingAs($other)->get(route('home'))->assertRedirect(route('profile'));
    $this->get(route('users.index'))->assertRedirect(route('profile'));
    $this->get(route('profile'))->assertOk()->assertSee(__('settings.two_factor.must_enable'));

    // Gli altri ruoli non sono toccati; chi l'ha attivata lavora normalmente
    $this->actingAs($tech)->get(route('home'))->assertOk();
    $this->actingAs($this->admin)->get(route('users.index'))->assertOk();

    // Attivata, l'amministratore torna a lavorare
    withTwoFactor($other);
    $this->actingAs($other)->get(route('home'))->assertOk();
});

it('con l\'obbligo un amministratore non può togliersi la verifica in due passaggi', function () {
    withTwoFactor($this->admin);
    settings()->set(AdminTwoFactor::SETTING, true);
    $this->actingAs($this->admin);

    Livewire::test(Profile::class)->set('twoFactorPassword', 'Password1234')->call('disableTwoFactor')
        ->assertHasErrors('twoFactorPassword')->assertSee(__('settings.two_factor.cannot_disable'));
    expect($this->admin->fresh()->hasTwoFactor())->toBeTrue();

    settings()->set(AdminTwoFactor::SETTING, false);
    Livewire::test(Profile::class)->set('twoFactorPassword', 'Password1234')->call('disableTwoFactor')->assertHasNoErrors();
    expect($this->admin->fresh()->hasTwoFactor())->toBeFalse();
});
