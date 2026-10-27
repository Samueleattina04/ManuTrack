<?php

/*
 * Calcolo delle scadenze, senza database dove possibile.
 */

use App\Enums\IntervalUnit;
use App\Models\MaintenancePlan;
use App\Support\Preventive\PlanSchedule;
use Carbon\CarbonImmutable;

function timePlan(string $start, int $n, string $unit, string $mode = 'fisso'): MaintenancePlan
{
    $p = new MaintenancePlan(['trigger' => 'tempo', 'schedule_mode' => $mode, 'interval_value' => $n, 'interval_unit' => $unit, 'lead_days' => 7, 'is_active' => true]);
    $p->start_on = $start;
    $p->next_due_on = $start;

    return $p;
}

beforeEach(fn () => $this->s = new PlanSchedule);

it('mesi senza sforare: 31 gennaio, poi fine febbraio, poi 31 marzo', function () {
    $p = timePlan('2026-01-31', 1, 'mesi');
    $d1 = $this->s->occurrenceAfter($p, CarbonImmutable::parse('2026-01-31'));
    $d2 = $this->s->occurrenceAfter($p, $d1);
    expect($d1->toDateString())->toBe('2026-02-28')->and($d2->toDateString())->toBe('2026-03-31');

    // Anno bisestile
    expect($this->s->occurrenceAfter(timePlan('2028-01-31', 1, 'mesi'), CarbonImmutable::parse('2028-01-31'))->toDateString())->toBe('2028-02-29');
});

it('date fisse anche dopo molti anni non slittano', function () {
    $p = timePlan('2020-01-31', 1, 'mesi');
    expect($this->s->occurrenceAfter($p, CarbonImmutable::parse('2029-04-30'))->toDateString())->toBe('2029-05-31');

    $y = timePlan('2020-02-29', 1, 'anni');
    expect($this->s->occurrenceAfter($y, CarbonImmutable::parse('2023-03-01'))->toDateString())->toBe('2024-02-29');
});

it('unità diverse', function (string $unit, int $n, string $expected) {
    expect($this->s->occurrenceAfter(timePlan('2026-03-10', $n, $unit), CarbonImmutable::parse('2026-03-10'))->toDateString())->toBe($expected);
})->with([
    ['giorni', 10, '2026-03-20'],
    ['settimane', 2, '2026-03-24'],
    ['mesi', 3, '2026-06-10'],
    ['anni', 1, '2027-03-10'],
]);

it('date fisse: in anticipo resta la scadenza successiva del calendario; in ritardo non recupera gli arretrati', function () {
    $p = timePlan('2026-01-15', 1, 'mesi');
    // Previsto il 15/03, fatto il 10/03 → 15/04
    expect($this->s->nextAfterDone($p, CarbonImmutable::parse('2026-03-15'), CarbonImmutable::parse('2026-03-10'))->toDateString())->toBe('2026-04-15');
    // Previsto il 15/03, fatto il 20/05 → 15/06, senza aprile e maggio
    expect($this->s->nextAfterDone($p, CarbonImmutable::parse('2026-03-15'), CarbonImmutable::parse('2026-05-20'))->toDateString())->toBe('2026-06-15');
});

it('dall\'ultima esecuzione: si conta dal giorno del completamento', function () {
    $p = timePlan('2026-01-15', 3, 'mesi', 'ultima');
    expect($this->s->nextAfterDone($p, CarbonImmutable::parse('2026-01-15'), CarbonImmutable::parse('2026-02-03'))->toDateString())->toBe('2026-05-03');
});

it('saltato: si conta dalla scadenza prevista, mai prima di domani', function () {
    $p = timePlan('2026-01-15', 1, 'mesi', 'ultima');
    expect($this->s->nextAfterSkip($p, CarbonImmutable::parse('2026-03-15'), CarbonImmutable::parse('2026-03-01'))->toDateString())->toBe('2026-04-15')
        ->and($this->s->nextAfterSkip($p, CarbonImmutable::parse('2026-03-15'), CarbonImmutable::parse('2026-06-20'))->toDateString())->toBe('2026-07-15');
    $f = timePlan('2026-01-15', 1, 'mesi');
    expect($this->s->nextAfterSkip($f, CarbonImmutable::parse('2026-03-15'), CarbonImmutable::parse('2026-03-01'))->toDateString())->toBe('2026-04-15');
});

it('contatore: soglie fisse o dall\'ultima lettura', function () {
    $fixed = new MaintenancePlan(['trigger' => 'contatore', 'schedule_mode' => 'fisso', 'meter_interval' => 500]);
    $fixed->next_meter_due = 1000;
    expect($this->s->nextMeterDue($fixed, 1030))->toBe(1500.0)   // fatto poco dopo la soglia
        ->and($this->s->nextMeterDue($fixed, 1720))->toBe(2000.0) // molto dopo: niente recuperi
        ->and($this->s->nextMeterDue($fixed, 950))->toBe(1500.0); // in anticipo

    $last = new MaintenancePlan(['trigger' => 'contatore', 'schedule_mode' => 'ultima', 'meter_interval' => 500]);
    $last->next_meter_due = 1000;
    expect($this->s->nextMeterDue($last, 1030))->toBe(1530.0);
});

it('anticipo: il piano è in scadenza dai giorni di anticipo in poi', function () {
    $p = timePlan('2026-03-20', 1, 'mesi');
    expect($this->s->isDue($p, CarbonImmutable::parse('2026-03-12')))->toBeFalse()
        ->and($this->s->isDue($p, CarbonImmutable::parse('2026-03-13')))->toBeTrue();
    $p->is_active = false;
    expect($this->s->isDue($p, CarbonImmutable::parse('2026-03-20')))->toBeFalse();
});

it('calendario: scadenze previste del mese', function () {
    $p = timePlan('2026-03-02', 1, 'settimane');
    $dates = array_map(fn ($o) => $o['date']->toDateString(), $this->s->projected($p, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31')));
    expect($dates)->toBe(['2026-03-02', '2026-03-09', '2026-03-16', '2026-03-23', '2026-03-30']);
});

it('IntervalUnit::add', function () {
    expect(IntervalUnit::Months->add(CarbonImmutable::parse('2026-08-31'), 6)->toDateString())->toBe('2027-02-28');
});
