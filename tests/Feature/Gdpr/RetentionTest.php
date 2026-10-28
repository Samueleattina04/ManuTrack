<?php

use App\Actions\Users\ManageUser;
use App\Livewire\Gdpr\RetentionSettings;
use App\Models\AuditLog;
use App\Models\DataExport;
use App\Support\Access\Access;
use App\Support\Gdpr\Retention;
use App\Support\Plant\AssetModel;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
});

it('salva le regole con il minimo per il registro e mostra cosa farebbe stanotte', function () {
    $this->get(route('gdpr.retention'))->assertOk()->assertSee(__('gdpr.retention.intro'))->assertSee('nessuna persona da anonimizzare')->assertDontSee(['gdpr.columns', 'gdpr.retention.', 'gdpr.erase.', 'gdpr.anonymize.', '{1}']);
    $this->actingAs(userWithRole(Access::MANAGER))->withSession(['auth.password_confirmed_at' => time()])->get(route('gdpr.retention'))->assertForbidden();
    $this->actingAs($this->admin);

    Livewire::test(RetentionSettings::class)->set('auditMonths', '3')->call('save')->assertHasErrors('auditMonths');
    $old = userWithRole(Access::TECHNICIAN, ['name' => 'Ex Tecnico Uno']);
    app(ManageUser::class)->setActive($this->admin, $old, false);
    $old->forceFill(['deactivated_at' => now()->subMonths(13)])->save();
    $recent = userWithRole(Access::TECHNICIAN);
    app(ManageUser::class)->setActive($this->admin, $recent, false);
    AuditLog::query()->forceCreate(['action' => 'work_orders.created', 'description' => 'vecchia', 'created_at' => now()->subMonths(30)]);

    Livewire::test(RetentionSettings::class)->set('anonymizeAfter', '12')->set('auditMonths', '24')->call('save')->assertHasNoErrors()
        ->assertDispatched('toast', message: __('gdpr.retention.saved'))
        ->assertSee(trans_choice('gdpr.retention.preview_users', 1, ['n' => 1]))
        ->assertSee(trans_choice('gdpr.retention.preview_audit', 1, ['n' => 1]));
    expect(app(Retention::class)->auditMonths())->toBe(24)
        ->and(AuditLog::query()->where('action', 'settings.retention')->exists())->toBeTrue();

    // Vuoto = per sempre
    Livewire::test(RetentionSettings::class)->set('anonymizeAfter', '')->set('auditMonths', '')->call('save')
        ->assertSee(trans_choice('gdpr.retention.preview_users', 0, ['n' => 0]));
});

it('il comando notturno anonimizza, accorcia il registro, elimina gli ZIP scaduti, e lo registra', function () {
    $old = userWithRole(Access::TECHNICIAN, ['name' => 'Ex Tecnico Uno', 'email' => 'ex.uno@example.test']);
    $order = makeOrder($this->admin, [$old->id], ['description' => 'Lo segue Ex Tecnico Uno']);
    app(ManageUser::class)->setActive($this->admin, $old, false);
    $old->forceFill(['deactivated_at' => now()->subMonths(7)])->save();
    AuditLog::query()->forceCreate(['action' => 'work_orders.created', 'description' => 'vecchia', 'created_at' => now()->subMonths(8)]);
    $disk = Storage::disk(AssetModel::disk());
    $disk->put('esportazioni/vecchio.zip', 'x');
    $export = DataExport::query()->forceCreate(['user_id' => $this->admin->id, 'status' => 'pronto', 'path' => 'esportazioni/vecchio.zip', 'expires_at' => now()->subDay()]);

    // Senza regole: nulla, nemmeno nel registro (lo ZIP scaduto sì)
    $this->artisan('manutrack:conservazione')->assertSuccessful();
    expect($old->refresh()->anonymized_at)->toBeNull()
        ->and(AuditLog::query()->where('description', 'vecchia')->exists())->toBeTrue()
        ->and($disk->exists('esportazioni/vecchio.zip'))->toBeFalse()
        ->and($export->refresh()->path)->toBeNull();

    app(Retention::class)->save(6, 6);
    // Di notte nessuno è collegato
    $this->app['auth']->forgetGuards();
    $this->artisan('manutrack:conservazione')->assertSuccessful()->expectsOutputToContain('1 persone anonimizzate');
    expect($old->refresh()->anonymized_at)->not->toBeNull()
        ->and($order->refresh()->description)->toBe('Lo segue '.__('gdpr.anonymize.pseudonym', ['id' => $old->id]))
        ->and(AuditLog::query()->where('description', 'vecchia')->exists())->toBeFalse();
    $log = AuditLog::query()->where('action', 'system.retention')->latest('id')->firstOrFail();
    expect($log->changes)->toMatchArray(['anonymized' => 1, 'audit_deleted' => 1])
        ->and(AuditLog::query()->where('action', 'users.anonymized')->value('user_id'))->toBeNull();

    // Seconda esecuzione: niente da fare, nessuna nuova riga
    $count = AuditLog::query()->count();
    $this->artisan('manutrack:conservazione')->assertSuccessful();
    expect(AuditLog::query()->count())->toBe($count);
});
