<?php

use App\Livewire\Settings\WorkCalendarEdit;
use App\Livewire\Settings\WorkCalendars;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\DowntimeEvent;
use App\Models\WorkCalendar;
use App\Models\WorkCalendarAssignment;
use App\Support\Access\Access;
use App\Support\WorkTime\CalendarEditor;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->actingAs($this->admin);
});

it('solo chi gestisce le impostazioni vede e modifica l\'orario di lavoro', function () {
    $calendar = app(CalendarEditor::class)->create('Turno unico', 'un_turno');
    $this->get(route('settings.worktime'))->assertOk()->assertSee('Turno unico')->assertSee(__('worktime.week_hours', ['h' => '40,0']));
    $this->get(route('settings.worktime.edit', $calendar))->assertOk();
    foreach ([Access::MANAGER, Access::TECHNICIAN, Access::VIEWER] as $role) {
        $user = userWithRole($role);
        $this->actingAs($user)->get(route('settings.worktime'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.worktime.edit', $calendar))->assertForbidden();
    }
});

it('dalla pagina: crea da un modello, assegna da una data, avvisa per le date passate', function () {
    $page = Livewire::test(WorkCalendars::class)->set('name', 'Due turni')->set('preset', 'due_turni')->call('create');
    $calendar = WorkCalendar::query()->where('name', 'Due turni')->firstOrFail();
    $page->assertRedirect(route('settings.worktime.edit', $calendar));
    expect($calendar->schedules()->first()->shifts()->count())->toBe(10)
        ->and($calendar->exceptions()->count())->toBeGreaterThanOrEqual(24);

    Livewire::test(WorkCalendars::class)->set('name', 'Due turni')->call('create')->assertHasErrors('name');

    $page = Livewire::test(WorkCalendars::class)
        ->set('assign.calendar', (string) $calendar->id)
        ->set('assign.valid_from', today()->subDays(3)->toDateString())
        ->assertSee(__('worktime.assign.past_warning'))
        ->call('saveAssignment');
    expect(WorkCalendarAssignment::query()->where('scope', 'azienda')->value('work_calendar_id'))->toBe($calendar->id)
        ->and(AuditLog::query()->where('action', 'settings.work_calendar_assigned')->exists())->toBeTrue();
    $this->get(route('settings.worktime'))->assertSee(__('worktime.assign.company'))->assertSee('Due turni');
});

it('pagina del calendario: orario, versioni, giorni speciali, blocco del passato', function () {
    $calendar = app(CalendarEditor::class)->create('Prova', 'vuoto');
    $page = Livewire::test(WorkCalendarEdit::class, ['calendar' => $calendar])
        ->call('addShift', 1)
        ->set('shifts.1.0', ['07:00', '15:00'])
        ->call('saveShifts')->assertHasNoErrors()->assertDispatched('toast', message: __('worktime.edit.saved_hours'));
    expect($calendar->schedules()->first()->shifts()->first()->only(['weekday', 'start', 'end']))->toBe(['weekday' => 1, 'start' => '07:00', 'end' => '15:00']);
    $page->call('addShift', 2)->set('shifts.2.0', ['7', '15:00'])->call('saveShifts')->assertHasErrors('shifts.2');

    $page->set('closure.from', today()->addDays(10)->toDateString())->set('closure.to', today()->addDays(12)->toDateString())->set('closure.label', 'Ferie')->call('addClosure')->assertHasNoErrors();
    expect($calendar->exceptions()->where('label', 'Ferie')->count())->toBe(3);
    $page->set('special.date', today()->addDays(20)->toDateString())->set('special.start', '08:00')->set('special.end', '12:00')->set('special.label', 'Inventario')->call('setSpecial')->assertHasNoErrors();

    // Il giorno dopo l'orario iniziale è in vigore: bloccato, si crea una versione nuova
    $this->travelTo(now()->addDays(2));
    $page = Livewire::test(WorkCalendarEdit::class, ['calendar' => $calendar->refresh()])
        ->assertSee(__('worktime.edit.version_locked'))
        ->call('saveShifts')->assertHasErrors('shifts');
    $page->set('newVersionFrom', today()->subDay()->toDateString())->call('createVersion')->assertHasErrors('newVersionFrom');
    $page->set('newVersionFrom', today()->addWeek()->toDateString())->call('createVersion')->assertHasNoErrors();
    expect($calendar->schedules()->count())->toBe(2);

    // Festività: togli → si lavora; rimetti → di nuovo festa
    $holiday = $calendar->exceptions()->where('source', 'festivita')->whereDate('date', '>=', today())->orderBy('date')->firstOrFail();
    $page->set('year', (int) $holiday->date->year)->call('removeDay', $holiday->id);
    expect($holiday->refresh()->kind)->toBe('lavorativo');
    $page->call('restoreDay', $holiday->id);
    expect($holiday->refresh()->kind)->toBe('chiuso');
});

it('dashboard, esportazione e rapporto: MTBF sulle ore di lavoro principale, 24 ore secondario', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-01 09:00:00'));
    $editor = app(CalendarEditor::class);
    $calendar = $editor->create('Giorno', 'vuoto');
    $editor->saveShifts($calendar->schedules()->first(), array_fill_keys([1, 2, 3, 4, 5], [['08:00', '16:00']]));
    $editor->assign('azienda', null, $calendar, '2026-01-01');
    $asset = Asset::factory()->create(['installed_on' => '2025-01-01']);
    foreach (['2026-03-03 09:00', '2026-03-10 09:00', '2026-03-17 09:00', '2026-03-24 09:00'] as $start) {
        DowntimeEvent::query()->create(['asset_id' => $asset->id, 'started_at' => $start, 'ended_at' => CarbonImmutable::parse($start)->addHour()]);
    }
    $this->travelTo(CarbonImmutable::parse('2026-04-10 12:00:00'));
    /*
     * Marzo 2026: 22 giorni feriali × 8 h = 176 h di lavoro; 4 fermi di 1 h in orario.
     * MTBF lavoro = (176 − 4) / 4 = 43 h; 24 ore = (743 − 4) / 4 = 184,75 h.
     */
    $this->get(route('kpi', ['periodo' => 'mese_prec']))->assertOk()
        ->assertSee(__('worktime.kpi.work_label'))
        ->assertSee('43,0')
        ->assertSee(__('worktime.kpi.h24_secondary', ['value' => '184,8 h']));

    $csv = $this->get(route('export.kpi', ['format' => 'csv', 'periodo' => 'mese_prec']))->streamedContent();
    expect($csv)->toContain('"'.__('export.kpi.mtbf_work').'";43;')->toContain('"'.__('export.kpi.mtbf_24').'";184,8;');

    $this->get(route('kpi.report', ['mese' => '2026-03']))->assertOk()->assertSee(__('worktime.kpi.work_label'))->assertSee('43,0 h');
});

it('il comando propone le festività senza ripristinare quelle tolte', function () {
    $editor = app(CalendarEditor::class);
    $calendar = $editor->create('Prova', 'vuoto');
    $calendar->exceptions()->where('source', 'festivita')->delete();
    $this->artisan('manutrack:festivita')->assertSuccessful();
    $year = (int) today()->year;
    expect($calendar->exceptions()->whereYear('date', $year)->count())->toBe($year >= 2026 ? 13 : 12);
    $this->artisan('manutrack:festivita')->assertSuccessful();
    expect($calendar->exceptions()->count())->toBe($calendar->exceptions()->distinct('date')->count('date'));
});
