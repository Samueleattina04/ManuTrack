<?php

use App\Actions\Users\ManageUser;
use App\Livewire\Gdpr\Anonymize;
use App\Livewire\Users\Index;
use App\Models\AuditLog;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use App\Support\Access\Access;
use App\Support\Audit\Audit;
use App\Support\Gdpr\PersonAnonymizer;
use App\Support\Kpi\KpiCalculator;
use App\Support\Kpi\KpiPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
    $this->tech = userWithRole(Access::TECHNICIAN, ['name' => 'Gino Esempio', 'email' => 'gino.esempio@example.test', 'phone' => '333 000 1111']);
});

/** Ordine con commento, tempi e righe del registro che nominano il tecnico */
function orderMentioning(User $admin, User $tech): WorkOrder
{
    $order = makeOrder($admin, [$tech->id], ['description' => 'Chiamare Gino Esempio (333 000 1111) prima di iniziare']);
    WorkOrderComment::query()->forceCreate(['work_order_id' => $order->id, 'user_id' => $tech->id, 'body' => 'Fatto da gino esempio, scrivere a gino.esempio@example.test']);
    TimeEntry::query()->forceCreate(['work_order_id' => $order->id, 'user_id' => $tech->id, 'started_at' => now()->subHours(3), 'ended_at' => now()->subHours(1), 'minutes' => 120]);
    Audit::log('users.updated', $tech, 'gino.esempio@example.test', ['nome' => ['Gino Esempio', 'Gino Esempió'], 'nota' => 'Rif. G. Esempio'], $admin);
    test()->actingAs($tech);
    Audit::log('work_orders.started', $order, 'Avviato');
    test()->actingAs($admin);

    return $order;
}

it('solo l\'amministratore, con la password riconfermata', function () {
    $this->get(route('gdpr.anonymize'))->assertOk()->assertSee(__('gdpr.anonymize.intro'));
    $manager = userWithRole(Access::MANAGER);
    $manager->givePermissionTo('data.export');
    $this->actingAs($manager)->withSession(['auth.password_confirmed_at' => time()])->get(route('gdpr.anonymize'))->assertForbidden();
    $this->actingAs(userWithRole(Access::TECHNICIAN))->withSession(['auth.password_confirmed_at' => time()])->get(route('gdpr.anonymize'))->assertForbidden();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => 0])->get(route('gdpr.anonymize'))->assertRedirect(route('password.confirm'));
    expect(fn () => app(PersonAnonymizer::class)->anonymize($this->tech, [], $manager))->toThrow(ValidationException::class, __('gdpr.anonymize.admin_only'));
});

it('si anonimizza solo una persona disattivata, mai se stessi, una volta sola', function () {
    $a = app(PersonAnonymizer::class);
    expect(fn () => $a->anonymize($this->tech, [], $this->admin))->toThrow(ValidationException::class, __('gdpr.anonymize.deactivate_first'));
    expect(fn () => $a->anonymize($this->admin, [], $this->admin))->toThrow(ValidationException::class, __('gdpr.anonymize.not_self'));
    app(ManageUser::class)->setActive($this->admin, $this->tech, false);
    $a->anonymize($this->tech, [], $this->admin);
    expect(fn () => $a->anonymize($this->tech->refresh(), [], $this->admin))->toThrow(ValidationException::class, __('gdpr.anonymize.already'))
        // Non si riattiva
        ->and(fn () => app(ManageUser::class)->setActive($this->admin, $this->tech, true))->toThrow(ValidationException::class);
    expect(fn () => $a->anonymize(null, ['ab'], $this->admin))->toThrow(ValidationException::class, __('gdpr.anonymize.no_terms', ['min' => 3]));
});

it('toglie la persona ovunque e lascia storico e indicatori identici', function () {
    $this->freezeSecond();
    $order = orderMentioning($this->admin, $this->tech);
    DB::table('sessions')->insert(['id' => 'sess-x', 'user_id' => $this->tech->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'X', 'payload' => '', 'last_activity' => time()]);
    app(ManageUser::class)->setActive($this->admin, $this->tech, false);

    $kpi = fn () => (new KpiCalculator(KpiPeriod::preset('mese')))->mttr() + ['backlog' => (new KpiCalculator(KpiPeriod::preset('mese')))->backlog()];
    $before = $kpi();
    $minutes = TimeEntry::query()->where('user_id', $this->tech->id)->sum('minutes');

    $page = Livewire::test(Anonymize::class)->set('userId', (string) $this->tech->id)->set('extra', "G. Esempio\nGino Esempió\nab");
    $page->assertSee('Ordini: descrizione')->assertSee('Commenti degli ordini')->assertSee('Registro attività: modifiche')
        ->assertSee('Si cercano 5 parole.')->assertDontSee(['gdpr.columns', 'gdpr.retention.', 'gdpr.erase.', 'gdpr.anonymize.', '{1}']);
    $page->call('anonymize')->assertHasErrors('confirm');
    expect($this->tech->refresh()->anonymized_at)->toBeNull();
    $page->set('confirm', 'ANONIMIZZA')->call('anonymize')->assertHasNoErrors()->assertSee(__('gdpr.anonymize.pseudonym', ['id' => $this->tech->id]));

    $p = __('gdpr.anonymize.pseudonym', ['id' => $this->tech->id]);
    $tech = $this->tech->refresh();
    expect($tech->name)->toBe($p)
        ->and($tech->email)->toBe('anonimo-'.$tech->id.'@anonimo.invalid')
        ->and($tech->phone)->toBeNull()
        ->and($tech->roles()->count())->toBe(0)
        ->and($tech->anonymized_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $tech->id)->exists())->toBeFalse();

    // Nessuna traccia di nome, email o telefono in tutto il database
    foreach (PersonAnonymizer::TEXT_COLUMNS + ['users' => ['name', 'email', 'phone']] as $table => $columns) {
        foreach ($columns as $column) {
            foreach (['gino esempio', 'gino.esempio@example.test', '333 000 1111', 'g. esempio', 'esempi'] as $old) {
                expect(DB::table($table)->whereRaw('LOWER(CAST('.$column.' AS CHAR)) LIKE ?', ['%'.$old.'%'])->exists())->toBeFalse("$table.$column contiene $old");
            }
        }
    }
    expect($order->refresh()->description)->toBe('Chiamare '.$p.' ('.$p.') prima di iniziare')
        ->and(AuditLog::query()->where('user_id', $tech->id)->whereNotNull('ip')->exists())->toBeFalse();

    // Storico e indicatori coerenti: stesso ordine assegnato, stessi tempi, stessi KPI
    expect($order->assignees()->pluck('users.id')->all())->toBe([$tech->id])
        ->and(TimeEntry::query()->where('user_id', $tech->id)->sum('minutes'))->toBe($minutes)
        ->and($kpi())->toBe($before);
    $this->get(route('work-orders.show', $order))->assertOk()->assertSee($p)->assertDontSee('Gino');

    // Il registro dice cosa è successo, non chi era
    $log = AuditLog::query()->where('action', 'users.anonymized')->firstOrFail();
    expect(json_encode($log->getAttributes()))->not->toContain('Gino')->not->toContain('example.test')
        ->and($log->changes['sostituzioni'])->toHaveKey('work_orders.description');
});

it('senza account: sostituisce solo il nome nei testi', function () {
    $order = makeOrder($this->admin, [], ['description' => 'Avvisare Tizia Prova del reparto esterno']);
    $result = app(PersonAnonymizer::class)->anonymize(null, ['Tizia Prova'], $this->admin);
    expect($order->refresh()->description)->toBe('Avvisare '.__('gdpr.anonymize.pseudonym_generic').' del reparto esterno')
        ->and($result['replaced'])->toBe(['work_orders.description' => 1]);
});

it('nell\'elenco persone: "Anonimizza" solo per i disattivati e solo per l\'amministratore', function () {
    $list = fn () => Livewire::test(Index::class)->set('status', 'inactive');
    $list()->assertDontSee(__('users.anonymize'));
    app(ManageUser::class)->setActive($this->admin, $this->tech, false);
    $list()->assertSee(__('users.anonymize'))->assertSee(route('gdpr.anonymize', ['persona' => $this->tech->id]), false);
    $manager = userWithRole(Access::MANAGER);
    $manager->givePermissionTo('users.manage');
    $this->actingAs($manager);
    $list()->assertSee('Gino Esempio')->assertDontSee(__('users.anonymize'));
    // Dopo: niente azioni sull'account anonimizzato
    $this->actingAs($this->admin);
    app(PersonAnonymizer::class)->anonymize($this->tech->refresh(), [], $this->admin);
    $list()->assertSee(__('users.anonymized'))->assertDontSee(__('users.anonymize'));
});
