<?php

use App\Livewire\Users\Index;
use App\Livewire\Users\Roles;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Access\Access;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/*
 * Controllo di sicurezza: persone disattivate con una pagina già aperta e permesso
 * "users.manage" dato a un ruolo diverso dall'amministratore.
 */

beforeEach(function () {
    $this->admin = installManuTrack();
    Notification::fake();
});

it('una persona disattivata con la pagina già aperta non può più fare nulla con Livewire', function () {
    $tech = userWithRole(Access::TECHNICIAN);
    $order = makeOrder($this->admin, [$tech->id]);
    $this->actingAs($tech);
    $snapshot = livewireSnapshot($this->get(route('work-orders.show', $order)), 'work-orders.show');

    // Prima della disattivazione l'azione funziona (la prova non è vuota)
    livewireCall($this, $snapshot, [['addComment']], ['comment' => 'prima'])->assertOk();
    $tech->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

    livewireCall($this, $snapshot, [['addComment']], ['comment' => 'dopo la disattivazione'])->assertRedirect(route('login'));

    expect($order->comments()->pluck('body')->all())->toBe(['prima'])
        ->and(auth()->check())->toBeFalse();
});

it('con "users.manage" un responsabile gestisce le persone ma non diventa amministratore', function () {
    Role::findByName(Access::MANAGER)->givePermissionTo('users.manage');
    $manager = userWithRole(Access::MANAGER);
    $tech = userWithRole(Access::TECHNICIAN);
    $this->actingAs($manager);
    $page = Livewire::test(Index::class);

    // Le operazioni normali restano possibili
    $page->call('changeRole', $tech->id, Access::STOREKEEPER);
    expect($tech->fresh()->roleName())->toBe(Access::STOREKEEPER);
    $page->set('inviteEmail', 'nuovo.tecnico@example.test')->set('inviteRole', Access::TECHNICIAN)->call('invite')->assertHasNoErrors();

    // Nominare amministratori, cambiare il proprio ruolo, toccare gli amministratori: no
    $page->call('changeRole', $manager->id, Access::ADMIN);
    $page->call('changeRole', $tech->id, Access::ADMIN);
    $page->call('changeRole', $this->admin->id, Access::VIEWER);
    $page->call('changeRole', $manager->id, Access::VIEWER);
    $page->call('toggleActive', $this->admin->id);
    $this->admin->forceFill(['two_factor_secret' => 'segreto', 'two_factor_confirmed_at' => now()])->save();
    $page->call('resetTwoFactor', $this->admin->id);
    $page->call('sendPasswordReset', $this->admin->id);
    $page->set('inviteEmail', 'amico@example.test')->set('inviteRole', Access::ADMIN)->call('invite')->assertHasErrors('inviteEmail');

    expect($manager->fresh()->roleName())->toBe(Access::MANAGER)
        ->and($tech->fresh()->roleName())->toBe(Access::STOREKEEPER)
        ->and($this->admin->fresh()->roleName())->toBe(Access::ADMIN)
        ->and($this->admin->fresh()->is_active)->toBeTrue()
        ->and($this->admin->fresh()->two_factor_secret)->not->toBeNull()
        ->and(Invitation::query()->where('role', Access::ADMIN)->exists())->toBeFalse();
    Notification::assertNothingSentTo($this->admin);

    // Un invito da amministratore fatto dall'amministratore non si rinnova da un altro ruolo
    $this->actingAs($this->admin);
    Livewire::test(Index::class)->set('inviteEmail', 'nuovo.admin@example.test')->set('inviteRole', Access::ADMIN)->call('invite')->assertHasNoErrors();
    $invitation = Invitation::query()->where('email', 'nuovo.admin@example.test')->sole();
    $this->actingAs($manager);
    Livewire::test(Index::class)->call('resend', $invitation->id)->assertDispatched('toast', message: __('users.admin_only'), tone: 'ko');
    expect($invitation->fresh()->revoked_at)->toBeNull()->and(Invitation::query()->where('email', 'nuovo.admin@example.test')->count())->toBe(1);
});

it('con "users.manage" la matrice dei permessi si vede ma la cambia solo l\'amministratore', function () {
    Role::findByName(Access::MANAGER)->givePermissionTo('users.manage');
    $manager = userWithRole(Access::MANAGER);
    $this->actingAs($manager)->get(route('roles.index'))->assertOk()->assertSee(__('access.admin_only_edit'));

    Livewire::test(Roles::class)->call('toggle', Access::MANAGER, 'system.update')->assertForbidden();
    Livewire::test(Roles::class)->call('toggle', Access::MANAGER, 'data.export')->assertForbidden();
    Livewire::test(Roles::class)->call('restoreDefaults', Access::TECHNICIAN)->assertForbidden();

    $manager = User::query()->find($manager->id);
    expect($manager->can('system.update'))->toBeFalse()->and($manager->can('data.export'))->toBeFalse();

    $this->actingAs($this->admin);
    Livewire::test(Roles::class)->call('toggle', Access::MANAGER, 'reports.export')->assertOk();
});

it('cambiando la password si chiudono le altre sessioni e i "ricordami", non quella in uso', function () {
    $tech = userWithRole(Access::TECHNICIAN, ['password' => Hash::make('Password1234'), 'remember_token' => 'vecchio-codice']);

    // Un'altra sessione della stessa persona (per esempio rimasta aperta su un PC condiviso)
    $this->actingAs($tech)->get('/')->assertOk();
    $other = session()->all();

    // Dalla propria sessione la persona cambia la password
    $this->flushSession();
    $snapshot = livewireSnapshot($this->actingAs($tech)->get(route('profile')), 'profile');
    livewireCall($this, $snapshot, [['changePassword']], ['currentPassword' => 'Password1234', 'password' => 'NuovaPassword99', 'password_confirmation' => 'NuovaPassword99'])->assertOk();
    expect(Hash::check('NuovaPassword99', $tech->fresh()->password))->toBeTrue();
    $this->get('/')->assertOk();
    expect($tech->fresh()->remember_token)->not->toBe('vecchio-codice');

    // La vecchia sessione viene chiusa alla richiesta successiva
    $this->flushSession();
    $this->withSession($other)->actingAs(User::query()->find($tech->id))->get('/')->assertRedirect(route('login'));
    $this->assertGuest();
});
