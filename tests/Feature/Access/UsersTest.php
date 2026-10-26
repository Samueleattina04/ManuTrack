<?php

use App\Livewire\Users\Index;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Support\Access\Access;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
    Notification::fake();
});

function inviteAndCatchToken(User $admin, string $email = 'nuovo@example.test', string $role = Access::TECHNICIAN): string
{
    Livewire::actingAs($admin)->test(Index::class)
        ->set('inviteEmail', $email)->set('inviteRole', $role)->set('inviteName', 'Nuovo Tecnico')
        ->call('invite')->assertHasNoErrors();

    $token = null;
    Notification::assertSentTo(Invitation::query()->where('email', $email)->latest('id')->first(), InvitationNotification::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });
    auth()->logout();

    return $token;
}

it('invita una persona e salva solo l\'impronta del codice', function () {
    $token = inviteAndCatchToken($this->admin);

    $invitation = Invitation::query()->sole();
    expect($invitation->token_hash)->toBe(hash('sha256', $token))
        ->and($invitation->token_hash)->not->toContain($token)
        ->and($invitation->isPending())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'users.invited')->exists())->toBeTrue();
});

it('chi accetta l\'invito entra con il ruolo deciso', function () {
    $token = inviteAndCatchToken($this->admin, role: Access::STOREKEEPER);

    $this->get(route('invitation.show', $token))->assertOk()->assertSee('nuovo@example.test');
    $this->post(route('invitation.accept', $token), ['name' => 'Giulia Nuova', 'password' => 'Password1234', 'password_confirmation' => 'Password1234'])
        ->assertRedirect(route('home'));

    $user = User::query()->where('email', 'nuovo@example.test')->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->roleName())->toBe(Access::STOREKEEPER)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Invitation::query()->sole()->accepted_at)->not->toBeNull();

    // Il link non si può riusare
    auth()->logout();
    $this->get(route('invitation.show', $token))->assertSee(__('users.invite.invalid_title'));
    $this->post(route('invitation.accept', $token), ['name' => 'Altro', 'password' => 'Password1234', 'password_confirmation' => 'Password1234'])->assertNotFound();
});

it('rifiuta inviti scaduti, annullati o con codice sbagliato', function () {
    $token = inviteAndCatchToken($this->admin);
    $this->get(route('invitation.show', 'codice-inventato'))->assertSee(__('users.invite.invalid_title'));

    Invitation::query()->update(['expires_at' => now()->subMinute()]);
    $this->get(route('invitation.show', $token))->assertSee(__('users.invite.invalid_title'));

    $token2 = inviteAndCatchToken($this->admin, 'altro@example.test');
    Livewire::actingAs($this->admin)->test(Index::class)->call('revoke', Invitation::query()->where('email', 'altro@example.test')->value('id'));
    auth()->logout();
    $this->post(route('invitation.accept', $token2), ['name' => 'X', 'password' => 'Password1234', 'password_confirmation' => 'Password1234'])->assertNotFound();
});

it('un nuovo invito alla stessa email annulla il precedente', function () {
    $first = inviteAndCatchToken($this->admin);
    inviteAndCatchToken($this->admin);

    expect(Invitation::query()->pending()->count())->toBe(1);
    $this->get(route('invitation.show', $first))->assertSee(__('users.invite.invalid_title'));
});

it('non invita un\'email già registrata', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('inviteEmail', 'admin@example.test')->call('invite')
        ->assertHasErrors('inviteEmail');
});

it('cambia il ruolo e protegge l\'ultimo amministratore', function () {
    $tech = userWithRole(Access::TECHNICIAN);

    Livewire::actingAs($this->admin)->test(Index::class)->call('changeRole', $tech->id, Access::MANAGER);
    expect($tech->fresh()->roleName())->toBe(Access::MANAGER);

    Livewire::actingAs($this->admin)->test(Index::class)->call('changeRole', $this->admin->id, Access::TECHNICIAN)->assertDispatched('toast');
    expect($this->admin->fresh()->isAdmin())->toBeTrue();

    Livewire::actingAs($this->admin)->test(Index::class)->call('toggleActive', $this->admin->id);
    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('disattiva e riattiva una persona', function () {
    $tech = userWithRole(Access::TECHNICIAN);

    Livewire::actingAs($this->admin)->test(Index::class)->call('toggleActive', $tech->id);
    expect($tech->fresh()->is_active)->toBeFalse()->and($tech->fresh()->deactivated_at)->not->toBeNull();

    Livewire::actingAs($this->admin)->test(Index::class)->call('toggleActive', $tech->id);
    expect($tech->fresh()->is_active)->toBeTrue();
});

it('il responsabile vede le persone ma non le gestisce', function () {
    $manager = userWithRole(Access::MANAGER);

    Livewire::actingAs($manager)->test(Index::class)->assertOk()->assertDontSee(__('users.invite.button'));
    Livewire::actingAs($manager)->test(Index::class)->set('inviteEmail', 'x@example.test')->call('invite')->assertForbidden();
    Livewire::actingAs($manager)->test(Index::class)->call('toggleActive', $this->admin->id)->assertForbidden();
});

it('filtra per ricerca, ruolo e stato', function () {
    userWithRole(Access::TECHNICIAN, ['name' => 'Marta Tecnica']);
    userWithRole(Access::VIEWER, ['name' => 'Oscar Osservatore', 'is_active' => false]);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('search', 'Marta')->assertSee('Marta Tecnica')->assertDontSee('Oscar Osservatore')
        ->set('search', '')->set('status', 'inactive')->assertSee('Oscar Osservatore')->assertDontSee('Marta Tecnica')
        ->set('status', 'all')->set('role', Access::TECHNICIAN)->assertSee('Marta Tecnica')->assertDontSee('Oscar Osservatore');
});
