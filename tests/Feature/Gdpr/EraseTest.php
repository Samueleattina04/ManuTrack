<?php

use App\Livewire\Gdpr\Erase;
use App\Support\Access\Access;
use App\Support\Gdpr\Eraser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
});

afterEach(function () {
    @unlink(Eraser::reportPath());
});

it('mostra cosa verrà cancellato e cosa resta fuori; solo l\'amministratore', function () {
    $this->get(route('gdpr.erase'))->assertOk()
        ->assertSee(__('gdpr.erase.outside_title'))->assertSee(__('gdpr.erase.outside')[0])
        ->assertSee('CANCELLA OFFICINE PROVA')->assertSee(__('gdpr.erase.no_export'));
    $manager = userWithRole(Access::MANAGER);
    $manager->givePermissionTo('data.export');
    $this->actingAs($manager)->withSession(['auth.password_confirmed_at' => time()])->get(route('gdpr.erase'))->assertForbidden();
});

it('conferma forte: presa d\'atto, frase esatta e password; poi tutto cancellato e solo il rapporto', function () {
    Storage::disk('local')->put('allegati/prova.txt', 'contenuto');
    makeOrder($this->admin);

    $page = Livewire::test(Erase::class)->set('phrase', 'CANCELLA OFFICINE PROVA')->set('password', 'Password1234');
    $page->call('erase')->assertHasErrors('understood');
    $page->set('understood', true)->set('phrase', 'cancella officine prova')->call('erase')->assertHasErrors('phrase');
    $page->set('phrase', 'CANCELLA OFFICINE PROVA')->set('password', 'sbagliata')->call('erase')->assertHasErrors('password')->assertSet('password', '');
    expect(Eraser::erased())->toBeFalse()->and(Schema::hasTable('users'))->toBeTrue();

    $page->set('password', 'Password1234')->call('erase')->assertHasNoErrors()->assertRedirect('/');

    expect(Eraser::erased())->toBeTrue()
        ->and(Schema::hasTable('users'))->toBeFalse()->and(Schema::hasTable('work_orders'))->toBeFalse()->and(Schema::hasTable('migrations'))->toBeFalse()
        ->and(Storage::disk('local')->exists('allegati/prova.txt'))->toBeFalse();
    $report = json_decode((string) file_get_contents(Eraser::reportPath()), true);
    expect($report['righe_cancellate'])->toBeGreaterThan(0)
        ->and($report['tabelle_cancellate'])->toHaveKeys(['users', 'work_orders'])
        ->and($report['non_cancellato'])->toHaveCount(4)
        ->and(json_encode($report))->not->toContain('admin@example.test')->not->toContain('Amministratore Prova')->not->toContain('Officine');

    // Ogni pagina ora mostra solo il rapporto, con 410
    $this->get('/')->assertStatus(410)->assertSee('Installazione di ManuTrack cancellata')->assertSee('Cosa resta fuori da ManuTrack');
    $this->get('/login')->assertStatus(410);
    // E il cron non esegue più nulla (non c'è più un database)
    $schedule = new Schedule;
    $this->app->instance(Schedule::class, $schedule);
    Illuminate\Support\Facades\Schedule::clearResolvedInstances();
    require base_path('routes/console.php');
    expect($schedule->events())->toBe([]);
});

it('dal terminale serve la frase; una seconda volta non fa nulla', function () {
    $this->artisan('manutrack:cancella-tutto')->assertFailed();
    $this->artisan('manutrack:cancella-tutto', ['--conferma' => 'CANCELLA ALTRA'])->assertFailed();
    expect(Eraser::erased())->toBeFalse();
    $this->artisan('manutrack:cancella-tutto', ['--conferma' => 'CANCELLA OFFICINE PROVA'])->assertSuccessful();
    expect(Eraser::erased())->toBeTrue()->and(json_decode((string) file_get_contents(Eraser::reportPath()), true)['richiesta_da'])->toBe('comando dal terminale');
    $this->artisan('manutrack:cancella-tutto', ['--conferma' => 'CANCELLA OFFICINE PROVA'])->assertSuccessful()->expectsOutputToContain(__('gdpr.erase.already'));
});
