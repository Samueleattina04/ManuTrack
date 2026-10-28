<?php

use App\Jobs\ExportCompanyData;
use App\Models\AuditLog;
use App\Models\DataExport;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\Gdpr\DataExporter;
use App\Support\Gdpr\Eraser;
use App\Support\Gdpr\Retention;
use App\Support\Plant\AssetModel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
 * Controllo di sicurezza 0.6: cancellazione totale, esportazione e anonimizzazione con
 * richieste costruite a mano (rotte HTTP e chiamate dirette a /livewire/update con lo
 * snapshot preso dalla pagina dell'amministratore o modificato), per ogni ruolo diverso
 * dall'amministratore, per un responsabile con data.export o settings.manage e per un
 * amministratore senza password riconfermata. Nulla deve cambiare nel database e su disco.
 */

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->target = userWithRole(Access::TECHNICIAN, ['name' => 'Persona Uscita', 'email' => 'uscita@example.test', 'is_active' => false, 'deactivated_at' => now()->subYear()]);
    Storage::disk('local')->put('attachments/prova/documento.txt', 'contenuto');

    // Snapshot delle quattro pagine, presi dall'amministratore con la password riconfermata
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
    $this->snapshots = [
        'erase' => livewireSnapshot($this->get(route('gdpr.erase')), 'gdpr.erase'),
        'anonymize' => livewireSnapshot($this->get(route('gdpr.anonymize', ['persona' => $this->target->id])), 'gdpr.anonymize'),
        'exports' => livewireSnapshot($this->get(route('gdpr.exports')), 'gdpr.data-exports'),
        'retention' => livewireSnapshot($this->get(route('gdpr.retention')), 'gdpr.retention-settings'),
    ];
    $this->ready = DataExport::query()->forceCreate(['user_id' => $this->admin->id, 'status' => 'pronto', 'path' => 'exports/esportazione-prova.zip', 'expires_at' => now()->addDays(3)]);
    Storage::disk(AssetModel::disk())->put('exports/esportazione-prova.zip', 'zip');
    app('auth')->forgetGuards();
});

afterEach(function () {
    @unlink(Eraser::reportPath());
});

/** Le chiamate che, se riuscissero, cambierebbero qualcosa */
function gdprAttacks(array $snapshots, int $targetId, int $exportId): array
{
    return [
        'cancellazione' => [$snapshots['erase'], [['erase']], ['understood' => true, 'phrase' => 'CANCELLA OFFICINE PROVA', 'password' => 'Password1234']],
        'cancellazione, snapshot modificato' => [tamperSnapshot($snapshots['erase'], ['understood' => true, 'phrase' => 'CANCELLA OFFICINE PROVA']), [['erase']], []],
        'anonimizzazione' => [$snapshots['anonymize'], [['anonymize']], ['userId' => (string) $targetId, 'confirm' => 'ANONIMIZZA']],
        'anonimizzazione, snapshot modificato' => [tamperSnapshot($snapshots['anonymize'], ['userId' => (string) $targetId, 'confirm' => 'ANONIMIZZA']), [['anonymize']], []],
        'esportazione' => [$snapshots['exports'], [['start']], []],
        'esportazione cancellata' => [$snapshots['exports'], [['delete', [$exportId]]], []],
        'conservazione' => [$snapshots['retention'], [['save']], ['anonymizeAfter' => '1', 'auditMonths' => '6']],
    ];
}

/** Stato da confrontare prima e dopo i tentativi */
function gdprState(): array
{
    return [
        'tables' => Schema::hasTable('users'),
        'erased' => Eraser::erased(),
        'anonymized' => User::query()->whereNotNull('anonymized_at')->count(),
        'exports' => DataExport::query()->orderBy('id')->get(['id', 'status'])->toArray(),
        'zip' => Storage::disk(AssetModel::disk())->exists('exports/esportazione-prova.zip'),
        'file' => Storage::disk('local')->exists('attachments/prova/documento.txt'),
        'retention' => [app(Retention::class)->anonymizeAfterMonths(), app(Retention::class)->auditMonths()],
    ];
}

dataset('non amministratori', [
    'responsabile' => [fn () => userWithRole(Access::MANAGER)],
    'tecnico' => [fn () => userWithRole(Access::TECHNICIAN)],
    'magazziniere' => [fn () => userWithRole(Access::STOREKEEPER)],
    'richiedente' => [fn () => userWithRole(Access::REQUESTER)],
    'osservatore' => [fn () => userWithRole(Access::VIEWER)],
    'responsabile con settings.manage' => [fn () => tap(userWithRole(Access::MANAGER))->givePermissionTo('settings.manage')],
]);

it('chi non è amministratore non cancella, non anonimizza e non esporta nemmeno con richieste costruite a mano', function (Closure $makeUser) {
    $before = gdprState();
    $user = $makeUser();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    foreach (['gdpr.exports', 'gdpr.anonymize', 'gdpr.retention', 'gdpr.erase'] as $route) {
        $this->get(route($route))->assertForbidden();
    }
    $this->get(route('gdpr.exports.download', $this->ready))->assertForbidden();
    // Id inesistente: 404 prima del controllo del permesso (si scopre solo che l'id non esiste)
    expect($this->get(route('gdpr.exports.download', 999))->status())->toBeIn([403, 404]);

    foreach (gdprAttacks($this->snapshots, $this->target->id, $this->ready->id) as $label => [$snapshot, $calls, $updates]) {
        $status = livewireCall($this, $snapshot, $calls, $updates)->status();
        expect($status)->toBeIn([403, 419], $label);
    }

    expect(gdprState())->toBe($before);
})->with('non amministratori');

it('un responsabile con data.export esporta (permesso dato apposta) ma non cancella né anonimizza', function () {
    $before = gdprState();
    $manager = tap(userWithRole(Access::MANAGER))->givePermissionTo('data.export');
    $this->actingAs($manager)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('gdpr.erase'))->assertForbidden();
    $this->get(route('gdpr.anonymize'))->assertForbidden();
    $attacks = gdprAttacks($this->snapshots, $this->target->id, $this->ready->id);
    foreach (['cancellazione', 'cancellazione, snapshot modificato', 'anonimizzazione', 'anonimizzazione, snapshot modificato'] as $label) {
        [$snapshot, $calls, $updates] = $attacks[$label];
        expect(livewireCall($this, $snapshot, $calls, $updates)->status())->toBeIn([403, 419], $label);
    }

    expect(gdprState())->toBe($before);
});

it('l\'amministratore senza password riconfermata non usa le pagine né le azioni già aperte', function () {
    $before = gdprState();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
    $this->travel(3)->hours();
    $this->travel(5)->minutes();

    foreach (['gdpr.exports', 'gdpr.anonymize', 'gdpr.retention', 'gdpr.erase'] as $route) {
        $this->get(route($route))->assertRedirect(route('password.confirm'));
    }
    $this->get(route('gdpr.exports.download', $this->ready))->assertRedirect(route('password.confirm'));

    $attacks = gdprAttacks($this->snapshots, $this->target->id, $this->ready->id);
    unset($attacks['cancellazione, snapshot modificato'], $attacks['anonimizzazione, snapshot modificato']);
    foreach ($attacks as $label => [$snapshot, $calls, $updates]) {
        // Come il browser, e anche chiedendo JSON (Laravel risponderebbe 423 senza bloccare Livewire)
        livewireCall($this, $snapshot, $calls, $updates)->assertRedirect(route('password.confirm'));
        livewireCall($this, $snapshot, $calls, $updates, ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])->assertRedirect(route('password.confirm'));
    }

    expect(gdprState())->toBe($before);
    // Dopo la conferma si torna alla pagina, non all'indirizzo interno di Livewire
    expect(session('url.intended'))->toStartWith(url('/sistema/dati/'));
});

it('con la password riconfermata le stesse chiamate dell\'amministratore funzionano (le prove sopra non sono vuote)', function () {
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
    $attacks = gdprAttacks($this->snapshots, $this->target->id, $this->ready->id);

    livewireCall($this, ...$attacks['conservazione'])->assertOk();
    expect(app(Retention::class)->auditMonths())->toBe(6);
    livewireCall($this, ...$attacks['anonimizzazione'])->assertOk();
    expect($this->target->fresh()->anonymized_at)->not->toBeNull();
    livewireCall($this, ...$attacks['esportazione cancellata'])->assertOk();
    expect(DataExport::query()->find($this->ready->id))->toBeNull();
});

it('uno snapshot modificato a mano viene rifiutato anche all\'amministratore', function () {
    $before = gdprState();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
    $attacks = gdprAttacks($this->snapshots, $this->target->id, $this->ready->id);

    expect(livewireCall($this, ...$attacks['cancellazione, snapshot modificato'])->status())->toBe(419)
        ->and(livewireCall($this, ...$attacks['anonimizzazione, snapshot modificato'])->status())->toBe(419)
        ->and(gdprState())->toBe($before);
});

it('la cancellazione totale vuole la password anche all\'amministratore, e il tentativo sbagliato resta nel registro', function () {
    $before = gdprState();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);

    livewireCall($this, $this->snapshots['erase'], [['erase']], ['understood' => true, 'phrase' => 'CANCELLA OFFICINE PROVA', 'password' => 'sbagliata'])->assertOk();

    expect(gdprState())->toBe($before)
        ->and(AuditLog::query()->where('action', 'system.erase_refused')->where('user_id', $this->admin->id)->exists())->toBeTrue();
});

it('lo ZIP di un\'esportazione si scarica solo se pronta e non scaduta, con qualunque id', function () {
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
    $other = DataExport::query()->forceCreate(['user_id' => null, 'status' => 'in_corso', 'path' => 'exports/esportazione-prova.zip']);
    $expired = DataExport::query()->forceCreate(['user_id' => null, 'status' => 'pronto', 'path' => 'exports/esportazione-prova.zip', 'expires_at' => now()->subMinute()]);

    $this->get(route('gdpr.exports.download', $this->ready))->assertOk();
    $this->get(route('gdpr.exports.download', $other))->assertNotFound();
    $this->get(route('gdpr.exports.download', $expired))->assertNotFound();
    $this->get(route('gdpr.exports.download', 999))->assertNotFound();
});

it('il job dell\'esportazione con un id arbitrario non prepara nulla', function () {
    $before = gdprState();

    (new ExportCompanyData(999))->handle(app(DataExporter::class));
    (new ExportCompanyData($this->ready->id))->handle(app(DataExporter::class));

    expect(gdprState())->toBe($before)
        ->and($this->ready->fresh()->status)->toBe('pronto');
});
