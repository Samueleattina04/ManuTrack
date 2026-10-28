<?php

use App\Models\AuditLog;
use App\Support\Access\Access;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
});

it('invia subito un\'email di prova all\'amministratore e lo registra', function () {
    config(['mail.default' => 'smtp']);
    Mail::fake();
    $this->get(route('system'))->assertOk()->assertSee(__('system.mail_test.button', ['email' => $this->admin->email]));

    $this->from(route('system'))->post(route('system.mail-test'))->assertRedirect(route('system'))
        ->assertSessionHas('status', __('system.mail_test.sent', ['email' => $this->admin->email, 'from' => config('mail.from.address')]));
    expect(AuditLog::query()->where('action', 'system.mail_test_sent')->exists())->toBeTrue();
});

it('con l\'invio "log" o "array" dice che le email non partono davvero', function () {
    config(['mail.default' => 'array']);
    $this->from(route('system'))->post(route('system.mail-test'))->assertSessionHas('error', __('system.mail_test.not_real', ['mailer' => 'array']));
});

it('mostra l\'errore del server di posta', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
    $this->from(route('system'))->post(route('system.mail-test'))->assertSessionHas('error');
    expect(session('error'))->toStartWith(mb_substr(__('system.mail_test.failed', ['message' => '']), 0, 30))
        ->and(AuditLog::query()->where('action', 'system.mail_test_failed')->exists())->toBeTrue();
});

it('al massimo 3 al minuto, e solo per chi gestisce gli aggiornamenti', function () {
    config(['mail.default' => 'array']);
    foreach (range(1, 3) as $i) {
        $this->post(route('system.mail-test'));
    }
    $this->from(route('system'))->post(route('system.mail-test'))->assertSessionHas('error', fn ($m) => ! str_contains($m, 'array'));

    $manager = userWithRole(Access::MANAGER);
    $this->actingAs($manager)->withSession(['auth.password_confirmed_at' => time()])->post(route('system.mail-test'))->assertForbidden();
});
