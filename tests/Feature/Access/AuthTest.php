<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Access\Access;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->admin = installManuTrack(['company' => 'Officine Accesso']);
});

it('manda alla pagina di accesso chi non è entrato', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get('/login')->assertOk()->assertSee('Accedi')->assertSee('con ManuTrack')->assertSee('Officine Accesso');
});

it('fa entrare con email e password e registra l\'accesso', function () {
    $this->post('/login', ['email' => 'ADMIN@example.test', 'password' => 'Password1234'])->assertRedirect('/');

    $this->assertAuthenticatedAs($this->admin);
    expect($this->admin->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'auth.login')->exists())->toBeTrue();
});

it('rifiuta una password sbagliata', function () {
    $this->post('/login', ['email' => 'admin@example.test', 'password' => 'sbagliata'])->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(AuditLog::query()->where('action', 'auth.failed')->exists())->toBeTrue();
});

it('non fa entrare un account disattivato', function () {
    $user = userWithRole(Access::TECHNICIAN, ['password' => 'Password1234']);
    $user->forceFill(['is_active' => false])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'Password1234'])
        ->assertSessionHasErrors(['email' => __('auth.inactive')]);
    $this->assertGuest();
});

it('chiude subito la sessione di chi viene disattivato', function () {
    $user = userWithRole(Access::TECHNICIAN);
    $this->actingAs($user)->get('/')->assertOk();

    $user->forceFill(['is_active' => false])->save();
    $this->get('/')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('blocca i tentativi ripetuti', function () {
    RateLimiter::clear('admin@example.test|127.0.0.1');
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'sbagliata']);
    }
    $this->post('/login', ['email' => 'admin@example.test', 'password' => 'Password1234'])->assertStatus(429);
    $this->assertGuest();
});

it('chiede il codice della verifica in due passaggi quando è attiva', function () {
    app(EnableTwoFactorAuthentication::class)($this->admin);
    $secret = decrypt($this->admin->fresh()->two_factor_secret);
    $google = new Google2FA;
    app(ConfirmTwoFactorAuthentication::class)($this->admin->fresh(), $google->getCurrentOtp($secret));

    $this->post('/login', ['email' => 'admin@example.test', 'password' => 'Password1234'])->assertRedirect(route('two-factor.login'));
    $this->assertGuest();

    $this->get(route('two-factor.login'))->assertOk()->assertSee('Verifica in due passaggi');
    $this->post(route('two-factor.login'), ['code' => '000000'])->assertRedirect(route('two-factor.login'));
    $this->assertGuest();

    // Un codice già usato non vale due volte: si usa quello del passo successivo (finestra di tolleranza)
    $this->post(route('two-factor.login'), ['code' => $google->oathTotp($secret, $google->getTimestamp() + 1)])->assertRedirect('/');
    $this->assertAuthenticatedAs($this->admin);
});

it('esce e registra l\'uscita', function () {
    $this->actingAs($this->admin)->post('/logout')->assertRedirect('/');
    $this->assertGuest();
    expect(AuditLog::query()->where('action', 'auth.logout')->exists())->toBeTrue();
});

it('non ha la registrazione libera', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'X', 'email' => 'x@example.test', 'password' => 'Password1234', 'password_confirmation' => 'Password1234'])->assertNotFound();
    expect(User::query()->count())->toBe(1);
});
