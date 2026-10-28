<?php

/*
 * MTBF sulle ore di lavoro (docs/KPI.md §2.9) con dati noti e risultati calcolati a mano.
 *
 * Periodo: marzo 2026 (743 ore: il 29/3 si passa all'ora legale). Fuso Europe/Rome.
 * 1/3/2026 è domenica.
 *
 * Calendario "Turni prova", assegnato all'ubicazione "Sito" (A sta in "Linea", dentro "Sito"):
 *   versione 1 (dall'inizio): lun–ven 06:00–22:00 (16 h); sabato 22:00–06:00 (8 h, finisce domenica)
 *   versione 2 (dal 30/3):     lun–ven 06:00–14:00 (8 h); niente sabato
 *   giovedì 19/3: chiuso (patrono, inserito a mano)
 *   venerdì 27/3: orario speciale 08:00–12:00 (4 h)
 *
 * Ore di lavoro di A in marzo:
 *   turno di sabato 28/2 22:00 → 1/3 06:00, la parte in marzo:              6 h
 *   lun–ven 2–27/3 con la versione 1: 20 giorni − il 19 chiuso − il 27 speciale
 *     = 18 × 16 h = 288 h, più il 27: 4 h                                  292 h
 *   sabati 7, 14, 21: 3 × 8 h = 24 h; sabato 28: 22:00 → 06:00 con il passaggio
 *     all'ora legale (alle 02:00 si va alle 03:00) = 7 h                     31 h
 *   lun 30 e mar 31 con la versione 2: 2 × 8 h                             16 h
 *   totale                                                                  345 h
 *
 * Fermi di A:
 *   f1 guasto  mar 3/3 20:00 → mer 4/3 08:00  (12 h; nelle ore di lavoro 20–22 e 06–08 = 4 h)
 *   f2 guasto  dom 15/3 10:00 → 12:00          (2 h; fuori orario: 0 h; il guasto conta)
 *   f3 guasto  sab 28/3 23:00 → dom 29/3 04:00 (5 h d'orologio − 1 h legale = 4 h, tutte nel turno)
 *   f4 pianificato (ordine preventivo) gio 12/3 10:00 → 14:00 (4 h, tutte in orario; non è un guasto)
 *   f5 guasto  gio 19/3 09:00 → 10:00          (1 h; giorno chiuso: 0 h; il guasto conta)
 *   guasti = 4; fermi nelle ore di lavoro = 4 + 0 + 4 + 4 + 0 = 12 h
 *
 *   MTBF ore di lavoro = (345 − 12) / 4 = 83,25 h
 *   MTBF 24 ore         = (743 − 23) / 4 = 180 h   (fermi in marzo: 12 + 2 + 4 + 4 + 1 = 23 h)
 *
 * Macchina B, in un'altra ubicazione senza calendario (24 ore), nessun fermo:
 *   flotta ore di lavoro = (333 + 743) / 4 = 269 h, base "misto"
 *   flotta 24 ore        = (720 + 743) / 4 = 365,75 h
 */

use App\Models\Asset;
use App\Models\DowntimeEvent;
use App\Models\Location;
use App\Models\WorkCalendar;
use App\Models\WorkCalendarAssignment;
use App\Models\WorkOrder;
use App\Support\Kpi\KpiCalculator;
use App\Support\Kpi\KpiPeriod;
use App\Support\WorkTime\CalendarEditor;
use App\Support\WorkTime\Holidays;
use App\Support\WorkTime\Intervals;
use App\Support\WorkTime\WorkTime;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->actingAs($this->admin);
    $this->editor = app(CalendarEditor::class);

    // Il calendario si prepara a febbraio: i giorni di marzo sono ancora futuri
    $this->travelTo(CarbonImmutable::parse('2026-02-10 09:00:00'));
    $this->calendar = $this->editor->create('Turni prova', 'vuoto');
    $first = $this->calendar->schedules()->first();
    $week = [];
    foreach ([1, 2, 3, 4, 5] as $d) {
        $week[$d] = [['06:00', '22:00']];
    }
    $week[6] = [['22:00', '06:00']];
    $this->editor->saveShifts($first, $week);
    $v2 = $this->editor->newVersion($this->calendar, '2026-03-30');
    $this->editor->saveShifts($v2, [1 => [['06:00', '14:00']], 2 => [['06:00', '14:00']], 3 => [['06:00', '14:00']], 4 => [['06:00', '14:00']], 5 => [['06:00', '14:00']]]);
    $this->editor->setDay($this->calendar, '2026-03-19', 'chiuso', 'Patrono');
    $this->editor->setDay($this->calendar, '2026-03-27', 'orario', 'Inventario', [['08:00', '12:00']]);

    $this->site = Location::query()->create(['name' => 'Sito']);
    $this->line = Location::query()->create(['name' => 'Linea', 'parent_id' => $this->site->id]);
    $other = Location::query()->create(['name' => 'Magazzino esterno']);
    $this->a = Asset::factory()->create(['code' => 'A', 'location_id' => $this->line->id, 'installed_on' => '2025-01-01']);
    $this->b = Asset::factory()->create(['code' => 'B', 'location_id' => $other->id, 'installed_on' => '2025-01-01']);

    $fermo = fn (string $from, string $to, ?WorkOrder $o = null) => DowntimeEvent::query()->create(['asset_id' => $this->a->id, 'started_at' => $from, 'ended_at' => $to, 'work_order_id' => $o?->id]);
    $fermo('2026-03-03 20:00:00', '2026-03-04 08:00:00');
    $fermo('2026-03-15 10:00:00', '2026-03-15 12:00:00');
    $fermo('2026-03-28 23:00:00', '2026-03-29 04:00:00');
    $preventive = new WorkOrder(['type' => 'preventivo', 'title' => 'Ingrassaggio', 'priority' => 'media', 'asset_id' => $this->a->id]);
    $preventive->save();
    $fermo('2026-03-12 10:00:00', '2026-03-12 14:00:00', $preventive);
    $fermo('2026-03-19 09:00:00', '2026-03-19 10:00:00');
});

function marchKpi(): KpiCalculator
{
    return new KpiCalculator(KpiPeriod::between('2026-03-01', '2026-03-31', CarbonImmutable::parse('2026-04-10 12:00:00')));
}

it('senza calendari assegnati nulla cambia: MTBF solo sulle 24 ore', function () {
    $this->travelTo(CarbonImmutable::parse('2026-04-10 12:00:00'));
    $m = marchKpi()->mtbf($this->a->id);
    expect($m['work'])->toBeNull()->and($m['value'])->toBe(180.0);
});

it('MTBF sulle ore di lavoro: marzo 2026 calcolato a mano', function () {
    $this->editor->assign('ubicazione', $this->site, $this->calendar, '2026-01-01', confirmPast: true);
    $this->travelTo(CarbonImmutable::parse('2026-04-10 12:00:00'));

    $time = (new WorkTime)->forAsset($this->a->refresh()->load('location'), CarbonImmutable::parse('2026-03-01 00:00:00'), CarbonImmutable::parse('2026-04-01 00:00:00'));
    expect(Intervals::seconds($time['intervals']) / 3600)->toEqual(345)
        ->and($time['calendar_days'])->toBe(31)->and($time['days'])->toBe(31);

    $m = marchKpi()->mtbf($this->a->id);
    expect($m['failures'])->toBe(4)
        ->and($m['value'])->toBe(180.0)
        ->and($m['work']['observed_hours'])->toBe(345.0)
        ->and($m['work']['uptime_hours'])->toBe(333.0)
        ->and($m['work']['value'])->toBe(83.25)
        ->and($m['work']['basis'])->toBe('lavoro')
        ->and($m['work']['insufficient'])->toBeFalse();

    // Flotta: B senza calendario conta 24 ore su 24
    $fleet = marchKpi()->mtbf();
    expect($fleet['value'])->toBe(365.75)
        ->and($fleet['work']['value'])->toBe(269.0)
        ->and($fleet['work']['basis'])->toBe('misto')
        ->and($fleet['work']['assets_with_calendar'])->toBe(1);
});

it('assegnazione da metà mese: prima vale il 24 ore, i giorni passati non cambiano', function () {
    $this->editor->assign('ubicazione', $this->site, $this->calendar, '2026-03-16');
    $this->travelTo(CarbonImmutable::parse('2026-04-10 12:00:00'));
    /*
     * 1–15/3 senza calendario: 15 × 24 = 360 h.
     * Dal 16/3: lun–ven 16–27 (10 giorni − il 19 − il 27) = 8 × 16 = 128 h, più il 27: 4 h;
     * sabati 21 (8 h) e 28 (7 h); lun 30 e mar 31: 16 h. Il turno di sabato 14 non conta:
     * quel giorno la macchina era ancora sulle 24 ore. Totale 360 + 132 + 15 + 16 = 523 h.
     * Fermi nelle ore: f1 12 h (24 ore), f2 2 h (24 ore), f3 4 h, f4 4 h (24 ore), f5 0 = 22 h.
     * MTBF = (523 − 22) / 4 = 125,25 h, base "misto" (16 giorni su 31 con il calendario).
     */
    $m = marchKpi()->mtbf($this->a->id);
    expect($m['work']['observed_hours'])->toBe(523.0)
        ->and($m['work']['value'])->toBe(125.25)
        ->and($m['work']['basis'])->toBe('misto');
});

it('eredità: ubicazione vuota eredita, la più vicina vince, macchina predisposta, azienda in cima', function () {
    $night = $this->editor->create('Solo notte', 'vuoto');
    $this->editor->saveShifts($night->schedules()->first(), [3 => [['22:00', '06:00']]]);
    $wt = fn () => new WorkTime;
    $chain = [$this->line->id, $this->site->id];

    expect((new WorkTime)->calendarFor($this->a->id, $chain, '2026-03-10'))->toBeNull();
    $this->editor->assign('azienda', null, $night, '2026-01-01', confirmPast: true);
    expect($wt()->calendarFor($this->a->id, $chain, '2026-03-10'))->toBe($night->id);
    $this->editor->assign('ubicazione', $this->site, $this->calendar, '2026-01-01', confirmPast: true);
    expect($wt()->calendarFor($this->a->id, $chain, '2026-03-10'))->toBe($this->calendar->id);
    // Ubicazione più vicina "eredita": resta quella del sito
    $this->editor->assign('ubicazione', $this->line, null, '2026-01-01', confirmPast: true);
    expect($wt()->calendarFor($this->a->id, $chain, '2026-03-10'))->toBe($this->calendar->id);
    // Dal 20/3 la linea ha il suo calendario
    $this->editor->assign('ubicazione', $this->line, $night, '2026-03-20');
    expect($wt()->calendarFor($this->a->id, $chain, '2026-03-19'))->toBe($this->calendar->id)
        ->and($wt()->calendarFor($this->a->id, $chain, '2026-03-20'))->toBe($night->id);
    // Macchina: previsto dal calcolo
    $this->editor->assign('macchina', null, $this->calendar, '2026-03-25', $this->a->id);
    expect($wt()->calendarFor($this->a->id, $chain, '2026-03-26'))->toBe($this->calendar->id);
    // Stessa data: si aggiorna la riga, non se ne crea un'altra
    $this->editor->assign('azienda', null, null, '2026-01-01', confirmPast: true);
    expect(WorkCalendarAssignment::query()->where('scope', 'azienda')->count())->toBe(1);
});

it('festività italiane: Pasquetta mobile e San Francesco dal 2026', function () {
    expect(Holidays::italian(2026))->toHaveKey('2026-04-06')->toHaveKey('2026-10-04')->toHaveCount(13)
        ->and(Holidays::italian(2025))->toHaveKey('2025-04-21')->not->toHaveKey('2025-10-04')
        ->and(Holidays::italian(2027)['2027-03-29'])->toBe('Lunedì dell\'Angelo');
    // Proposte alla creazione per quest'anno e il prossimo
    expect($this->calendar->exceptions()->where('source', 'festivita')->count())->toBe(26);
});

it('festività tolta: si lavora, e non viene riproposta', function () {
    $assunzione = $this->calendar->exceptions()->whereDate('date', '2026-08-15')->firstOrFail();
    $this->editor->removeDay($assunzione);
    expect($assunzione->refresh()->kind)->toBe('lavorativo');
    expect($this->editor->proposeHolidays($this->calendar, 2026))->toBe(0);
    expect($assunzione->refresh()->kind)->toBe('lavorativo');
    // Un giorno inserito a mano si toglie davvero
    $manual = $this->calendar->exceptions()->whereDate('date', '2026-03-19')->firstOrFail();
    $this->editor->removeDay($manual);
    expect($this->calendar->exceptions()->whereDate('date', '2026-03-19')->exists())->toBeFalse();
});

it('i mesi passati non cambiano: orario in vigore bloccato, giorni passati bloccati', function () {
    $first = $this->calendar->schedules()->first();
    $this->travelTo(CarbonImmutable::parse('2026-04-10 12:00:00'));
    // Orario già in vigore e non creato oggi: non si modifica
    expect(fn () => $this->editor->saveShifts($first->refresh(), [1 => [['07:00', '15:00']]]))->toThrow(ValidationException::class, __('worktime.errors.started'));
    // Nuovo orario: da oggi in poi e dopo l'ultimo
    expect(fn () => $this->editor->newVersion($this->calendar, '2026-04-01'))->toThrow(ValidationException::class)
        ->and(fn () => $this->editor->newVersion($this->calendar, '2026-13-01'))->toThrow(ValidationException::class);
    $v3 = $this->editor->newVersion($this->calendar, '2026-04-10');
    expect($v3->shifts()->count())->toBe(5); // copia della versione in vigore
    $this->editor->saveShifts($v3, [1 => [['07:00', '15:00']]]); // creato oggi: si corregge
    // Giorni passati
    expect(fn () => $this->editor->setDay($this->calendar, '2026-04-09', 'chiuso', 'X'))->toThrow(ValidationException::class)
        ->and(fn () => $this->editor->removeDay($this->calendar->exceptions()->whereDate('date', '2026-03-27')->firstOrFail()))->toThrow(ValidationException::class);
    // Calendario assegnato: non si elimina
    $this->editor->assign('azienda', null, $this->calendar, '2026-04-10');
    expect(fn () => $this->editor->delete($this->calendar))->toThrow(ValidationException::class, __('worktime.errors.in_use'));
    $spare = $this->editor->create('Mai usato', 'un_turno');
    $this->editor->delete($spare);
    expect(WorkCalendar::query()->whereKey($spare->id)->exists())->toBeFalse();
});

it('orari: formato, sovrapposizioni, turno di notte per ultimo, modelli pronti', function () {
    $s = $this->editor->create('Prova orari', 'vuoto')->schedules()->first();
    expect(fn () => $this->editor->saveShifts($s, [1 => [['8:00', '12:00']]]))->toThrow(ValidationException::class)
        ->and(fn () => $this->editor->saveShifts($s, [1 => [['08:00', '12:00'], ['11:00', '13:00']]]))->toThrow(ValidationException::class)
        ->and(fn () => $this->editor->saveShifts($s, [1 => [['22:00', '06:00'], ['23:00', '23:30']]]))->toThrow(ValidationException::class);
    $this->editor->saveShifts($s, [1 => [['06:00', '14:00'], ['14:00', '22:00'], ['22:00', '06:00']], 2 => [['', '']]]);
    expect($s->shifts()->count())->toBe(3);

    $three = $this->editor->create('Tre turni', 'tre_turni');
    $wt = new WorkTime;
    $hours = Intervals::seconds($wt->dayIntervals($three->id, '2026-02-13')) / 3600; // venerdì
    expect($hours)->toEqual(24)->and($wt->dayIntervals($three->id, '2026-02-14'))->toBe([]);
    $continuous = $this->editor->create('Continuo', 'continuo');
    $wt = new WorkTime; // i dati dei calendari si leggono una volta per istanza
    // 00:00 → 00:00 del giorno dopo: tempo reale, quindi 23 ore il giorno dell'ora legale e 25 quello dell'ora solare
    expect(Intervals::seconds($wt->dayIntervals($continuous->id, '2026-03-29')) / 3600)->toEqual(23)
        ->and(Intervals::seconds($wt->dayIntervals($continuous->id, '2026-10-25')) / 3600)->toEqual(25)
        ->and(Intervals::seconds($wt->dayIntervals($continuous->id, '2026-03-30')) / 3600)->toEqual(24);
});

it('chiusura su più giorni e orario speciale', function () {
    $n = $this->editor->addClosure($this->calendar, '2026-08-10', '2026-08-21', 'Ferie estive');
    expect($n)->toBe(12)->and($this->calendar->exceptions()->whereBetween('date', ['2026-08-10', '2026-08-21'])->where('kind', 'chiuso')->count())->toBe(12);
    // Ferragosto dentro le ferie: resta chiuso, con la descrizione delle ferie
    expect($this->calendar->exceptions()->whereDate('date', '2026-08-15')->first()->label)->toBe('Ferie estive');
    expect(fn () => $this->editor->addClosure($this->calendar, '2026-08-21', '2026-08-10', 'X'))->toThrow(ValidationException::class)
        ->and(fn () => $this->editor->setDay($this->calendar, '2026-05-04', 'orario', 'X', []))->toThrow(ValidationException::class);
});
