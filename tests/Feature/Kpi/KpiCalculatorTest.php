<?php

/*
 * Indicatori con dati noti e risultati calcolati a mano (docs/KPI.md).
 *
 * Periodo: marzo 2026, oggi = 10 aprile 2026 ore 12:00, fuso Europe/Rome.
 * Il 29 marzo 2026 si passa all'ora legale: marzo dura 31 × 24 − 1 = 743 ore.
 *
 * Macchine (ubicazione "Sito" > "Reparto 1" per A e B, "Reparto 2" per C):
 *   A  installata il 1/1/2025                       → osservata tutto marzo: 743 h
 *   B  inserita il 16/3 alle 00:00, senza installazione → dal 16/3 al 1/4: 16 giorni − 1 h = 383 h
 *   C  dismessa                                     → fuori dall'MTBF, dentro fermi/MTTR/costi
 *   D  installata il 1/5/2026                       → senza storico nel periodo
 *
 * Fermi:
 *   a1 A  28/2 22:00 → 1/3 04:00  guasto      (6 h; nel periodo 4 h; finisce in marzo: MTTR sì; comincia prima: MTBF no)
 *   a2 A  10/3 08:00 → 10/3 10:30 guasto      2,5 h
 *   a3 A  20/3 14:00 → 20/3 15:00 pianificato (ordine preventivo) 1 h
 *   a4 A  31/3 22:00 → 1/4 02:00  guasto      (nel periodo 2 h; finisce in aprile: MTTR no; comincia in marzo: MTBF sì)
 *   a5 A  15/3 09:00 → 15/3 12:00 guasto      3 h
 *   b1 B  20/3 10:00 → aperto     guasto      fino al 1/4 00:00 = 11 g 14 h − 1 h (ora legale) = 277 h
 *   b2 B  18/3 06:00 → 18/3 07:00 guasto      1 h
 *   c1 C  5/3 10:00 → 5/3 14:00   guasto      4 h
 */

use App\Enums\WorkOrderStatus as S;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\DowntimeEvent;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use App\Support\Kpi\KpiCalculator;
use App\Support\Kpi\KpiPeriod;
use Carbon\CarbonImmutable;

function kpiOrder(array $a): WorkOrder
{
    $o = new WorkOrder(['type' => $a['type'], 'title' => $a['title'] ?? 'Ordine', 'priority' => $a['priority'] ?? 'media', 'asset_id' => $a['asset'] ?? null, 'due_at' => $a['due'] ?? null, 'external_cost' => $a['external'] ?? null]);
    $o->created_at = $a['created'] ?? '2026-03-01 08:00:00';
    $o->maintenance_plan_id = $a['plan'] ?? null;
    $o->plan_due_on = $a['plan_due'] ?? null;
    $o->save();
    $o->forceFill(['status' => $a['status'], 'completed_at' => $a['completed'] ?? null, 'labor_rate' => $a['rate'] ?? null])->save();
    if (isset($a['minutes'])) {
        $o->timeEntries()->create(['user_id' => null, 'started_at' => $a['completed'], 'ended_at' => $a['completed'], 'minutes' => $a['minutes']]);
    }

    return $o;
}

beforeEach(function () {
    installManuTrack();
    settings()->set('costs.hourly_rate', 30);
    $this->travelTo(CarbonImmutable::parse('2026-04-10 12:00:00'));

    $site = Location::query()->create(['name' => 'Sito']);
    $l1 = Location::query()->create(['name' => 'Reparto 1', 'parent_id' => $site->id]);
    $l2 = Location::query()->create(['name' => 'Reparto 2', 'parent_id' => $site->id]);
    $this->site = $site;
    $this->l1 = $l1;
    $this->presses = AssetCategory::query()->create(['name' => 'Presse']);

    $this->A = Asset::factory()->create(['code' => 'A', 'location_id' => $l1->id, 'installed_on' => '2025-01-01', 'status' => 'operativa', 'asset_category_id' => $this->presses->id]);
    $this->B = Asset::factory()->create(['code' => 'B', 'location_id' => $l1->id, 'installed_on' => null, 'created_at' => '2026-03-16 00:00:00', 'status' => 'operativa']);
    $this->C = Asset::factory()->create(['code' => 'C', 'location_id' => $l2->id, 'installed_on' => '2020-01-01', 'status' => 'dismessa']);
    $this->D = Asset::factory()->create(['code' => 'D', 'location_id' => $l2->id, 'installed_on' => '2026-05-01', 'status' => 'operativa']);

    // Ordini (completati nel periodo salvo dove indicato)
    $plan = makePlan(['asset_id' => $this->A->id]);
    $o1 = kpiOrder(['type' => 'correttivo', 'asset' => $this->A->id, 'status' => S::Closed, 'completed' => '2026-03-10 11:00:00', 'rate' => 40, 'minutes' => 90, 'external' => 100]); // 60 + 100 = 160
    $o2 = kpiOrder(['type' => 'preventivo', 'asset' => $this->A->id, 'status' => S::Completed, 'completed' => '2026-03-20 15:30:00', 'minutes' => 60, 'plan' => $plan->id, 'plan_due' => '2026-03-20']); // 30 + ricambi 25 = 55, in tempo
    $part = makePart(['unit_cost' => 12.5]);
    StockMovement::query()->create(['part_id' => $part->id, 'warehouse_id' => makeWarehouse()->id, 'type' => 'prelievo', 'quantity' => -2, 'unit_cost' => 12.5, 'balance_after' => 0, 'work_order_id' => $o2->id]);
    kpiOrder(['type' => 'correttivo', 'asset' => $this->B->id, 'status' => S::Completed, 'completed' => '2026-03-31 23:30:00']); // o3
    kpiOrder(['type' => 'correttivo', 'asset' => $this->A->id, 'status' => S::Completed, 'completed' => '2026-04-01 00:00:00', 'external' => 999]); // o4: fuori (fine esclusa)
    kpiOrder(['type' => 'correttivo', 'asset' => $this->A->id, 'status' => S::Closed, 'completed' => '2026-02-28 23:59:59', 'external' => 999]); // o5: fuori
    $o6 = kpiOrder(['type' => 'preventivo', 'asset' => $this->A->id, 'status' => S::Cancelled, 'plan' => $plan->id, 'plan_due' => '2026-03-15']); // annullato: saltato
    kpiOrder(['type' => 'miglioramento', 'asset' => $this->C->id, 'status' => S::Closed, 'completed' => '2026-03-05 16:00:00', 'external' => 40]); // o7
    kpiOrder(['type' => 'preventivo', 'status' => S::Completed, 'completed' => '2026-03-12 10:00:00', 'external' => 10]); // o8 senza macchina
    kpiOrder(['type' => 'preventivo', 'asset' => $this->A->id, 'status' => S::Completed, 'completed' => '2026-03-25 10:00:00', 'plan' => $plan->id, 'plan_due' => '2026-03-22']); // o9 in ritardo
    kpiOrder(['type' => 'correttivo', 'asset' => $this->A->id, 'status' => S::Completed, 'completed' => '2026-03-26 10:00:00']); // o10
    kpiOrder(['type' => 'preventivo', 'asset' => $this->B->id, 'status' => S::Completed, 'completed' => '2026-03-27 10:00:00']); // o11
    // Arretrato (ordini attivi adesso)
    kpiOrder(['type' => 'correttivo', 'asset' => $this->A->id, 'status' => S::Open, 'created' => '2026-03-31 12:00:00', 'due' => '2026-04-05', 'priority' => 'alta']); // o12: 10 giorni, scaduto
    kpiOrder(['type' => 'correttivo', 'asset' => $this->B->id, 'status' => S::Planned, 'created' => '2026-04-09 12:00:00', 'due' => '2026-04-20']); // o13: 1 giorno
    kpiOrder(['type' => 'miglioramento', 'status' => S::InProgress, 'created' => '2026-04-08 12:00:00']); // o14: 2 giorni, senza macchina
    kpiOrder(['type' => 'preventivo', 'asset' => $this->A->id, 'status' => S::Planned, 'created' => '2026-03-21 12:00:00', 'due' => '2026-03-28', 'plan' => $plan->id, 'plan_due' => '2026-03-28']); // o15: 479 h, scaduto; piano: scaduto aperto

    $fermo = fn (Asset $a, string $from, ?string $to, ?WorkOrder $o = null) => DowntimeEvent::query()->create(['asset_id' => $a->id, 'started_at' => $from, 'ended_at' => $to, 'work_order_id' => $o?->id]);
    $fermo($this->A, '2026-02-28 22:00:00', '2026-03-01 04:00:00');
    $fermo($this->A, '2026-03-10 08:00:00', '2026-03-10 10:30:00');
    $fermo($this->A, '2026-03-20 14:00:00', '2026-03-20 15:00:00', $o2); // pianificato
    $fermo($this->A, '2026-03-31 22:00:00', '2026-04-01 02:00:00');
    $fermo($this->A, '2026-03-15 09:00:00', '2026-03-15 12:00:00');
    $fermo($this->B, '2026-03-20 10:00:00', null);
    $fermo($this->B, '2026-03-18 06:00:00', '2026-03-18 07:00:00');
    $fermo($this->C, '2026-03-05 10:00:00', '2026-03-05 14:00:00');

    $this->march = KpiPeriod::between('2026-03-01', '2026-03-31');
    $this->kpi = new KpiCalculator($this->march);
});

it('periodo: giorni interi in ora locale, 743 ore a marzo per l\'ora legale', function () {
    expect($this->march->start->format('Y-m-d H:i:s'))->toBe('2026-03-01 00:00:00')
        ->and($this->march->end->format('Y-m-d H:i:s'))->toBe('2026-04-01 00:00:00')
        ->and($this->march->hours())->toBe(743.0);

    // Mese corrente: dal 1° fino ad adesso
    $now = KpiPeriod::preset('mese');
    expect($now->start->format('Y-m-d H:i'))->toBe('2026-04-01 00:00')->and($now->end->format('Y-m-d H:i'))->toBe('2026-04-10 12:00')
        ->and(KpiPeriod::preset('mese_prec')->to->toDateString())->toBe('2026-03-31')
        ->and(KpiPeriod::preset('30g')->from->toDateString())->toBe('2026-03-12');

    expect(fn () => KpiPeriod::between('2026-05-01', '2026-05-31'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => KpiPeriod::between('2026-03-31', '2026-03-01'))->toThrow(InvalidArgumentException::class);
});

it('fermi: numero e durata dentro il periodo', function () {
    // 4 + 2,5 + 1 + 2 + 3 + 277 + 1 + 4 = 294,5 h; pianificato solo a3
    $d = $this->kpi->downtime();
    expect($d['count'])->toBe(8)
        ->and(round($d['hours'], 6))->toBe(294.5)
        ->and($d['planned_count'])->toBe(1)->and(round($d['planned_hours'], 6))->toBe(1.0)
        ->and($d['failure_count'])->toBe(7)->and(round($d['failure_hours'], 6))->toBe(293.5)
        ->and($d['open'])->toBe(1);
});

it('MTTR: guasti conclusi nel periodo, durata intera', function () {
    // a1 6 + a2 2,5 + a5 3 + b2 1 + c1 4 = 16,5 h su 5 → 3,3 h (esclusi a3 pianificato, a4 finito in aprile, b1 aperto)
    $m = $this->kpi->mttr();
    expect($m['n'])->toBe(5)->and(round($m['value'], 6))->toBe(3.3)->and($m['insufficient'])->toBeFalse();

    // Solo A: (6 + 2,5 + 3) / 3 = 3,8333…
    expect(round($this->kpi->mttr($this->A->id)['value'], 4))->toBe(3.8333);
});

it('MTBF: funzionamento / guasti, dismesse e macchine senza storico escluse', function () {
    // A: 743 − (4 + 2,5 + 1 + 2 + 3) = 730,5 h, guasti a2 a4 a5 = 3
    // B: 383 − (277 + 1) = 105 h, guasti b1 b2 = 2
    // (730,5 + 105) / 5 = 167,1 h; C dismessa e D senza storico escluse
    $m = $this->kpi->mtbf();
    expect($m['assets'])->toBe(2)
        ->and($m['failures'])->toBe(5)
        ->and(round($m['observed_hours'], 6))->toBe(1126.0)
        ->and(round($m['uptime_hours'], 6))->toBe(835.5)
        ->and(round($m['value'], 6))->toBe(167.1);

    expect(round($this->kpi->mtbf($this->A->id)['value'], 6))->toBe(243.5)       // 730,5 / 3
        ->and($this->kpi->mtbf($this->B->id)['insufficient'])->toBeTrue();        // 2 guasti < 3
});

it('arretrato: istantanea di adesso', function () {
    // o12 (10 g), o13 (1 g), o14 (2 g), o15 (479 h = 19,9583 g) → media 32,9583 / 4 = 8,2396 g; scaduti o12 e o15
    $b = $this->kpi->backlog();
    expect($b['open'])->toBe(4)->and($b['overdue'])->toBe(2)
        ->and(round($b['avg_age_days'], 4))->toBe(8.2396)
        ->and($b['by_type'])->toEqualCanonicalizing(['correttivo' => 2, 'miglioramento' => 1, 'preventivo' => 1])
        ->and($b['by_priority']['alta'])->toBe(1);
});

it('preventivo / correttivo: ordini completati nel periodo', function () {
    // Preventivi o2 o8 o9 o11 = 4; correttivi o1 o3 o10 = 3 (o4 o5 fuori, o6 annullato); miglioramento o7 a parte
    $mix = $this->kpi->mix();
    expect($mix['preventive'])->toBe(4)->and($mix['corrective'])->toBe(3)->and($mix['improvement'])->toBe(1)
        ->and(round($mix['preventive_pct'], 1))->toBe(57.1)
        ->and(round($mix['corrective_pct'], 1))->toBe(42.9);
});

it('costi per macchina', function () {
    // A: o1 160 + o2 55 + o9 0 + o10 0 = 215; C: 40; senza macchina: 10; B: 0 → totale 265
    $c = $this->kpi->costs();
    $rows = collect($c['rows'])->keyBy(fn ($r) => $r['asset_id'] ?? 0);
    expect(round($c['total']['total'], 2))->toBe(265.0)->and($c['total']['orders'])->toBe(8)
        ->and(round($rows[$this->A->id]['total'], 2))->toBe(215.0)
        ->and(round($rows[$this->A->id]['labor'], 2))->toBe(90.0)   // 1,5 h × 40 + 1 h × 30
        ->and(round($rows[$this->A->id]['parts'], 2))->toBe(25.0)
        ->and(round($rows[$this->A->id]['external'], 2))->toBe(100.0)
        ->and(round($rows[$this->C->id]['total'], 2))->toBe(40.0)
        ->and(round($rows[0]['total'], 2))->toBe(10.0)
        ->and($rows[$this->B->id]['orders'])->toBe(2);
});

it('rispetto dei piani nel periodo', function () {
    // o2 in tempo, o9 in ritardo, o6 saltato, o15 scaduto aperto → 1 / 4 = 25%
    $r = $this->kpi->compliance();
    expect([$r['on_time'], $r['late'], $r['skipped'], $r['overdue'], $r['total']])->toBe([1, 1, 1, 1, 4])
        ->and($r['rate'])->toBe(25.0)->and($r['insufficient'])->toBeFalse();
});

it('filtro per ubicazione: comprende le sotto-ubicazioni ed esclude gli ordini senza macchina', function () {
    $kpi = new KpiCalculator($this->march, $this->site->id);
    expect($kpi->downtime()['count'])->toBe(8); // A, B, C sono tutte sotto "Sito"

    $kpi = new KpiCalculator($this->march, $this->l1->id);  // solo A e B
    expect($kpi->downtime()['count'])->toBe(7)
        ->and(round($kpi->downtime()['hours'], 6))->toBe(290.5)
        ->and(round($kpi->costs()['total']['total'], 2))->toBe(215.0)
        ->and($kpi->mix()['preventive'])->toBe(3)              // o2 o9 o11 (o8 senza macchina escluso)
        ->and($kpi->backlog()['open'])->toBe(3);               // o14 senza macchina escluso

    $kpi = new KpiCalculator($this->march, null, $this->presses->id); // categoria Presse: solo A
    expect($kpi->downtime()['count'])->toBe(5)->and($kpi->mtbf()['failures'])->toBe(3);
});

it('dati insufficienti invece di numeri fuorvianti', function () {
    $day = new KpiCalculator(KpiPeriod::between('2026-03-10', '2026-03-10'));
    $mttr = $day->mttr();
    $mtbf = $day->mtbf();
    $mix = $day->mix();
    expect($mttr['value'])->toBeNull()->and($mttr['insufficient'])->toBeTrue()->and($mttr['n'])->toBe(1)
        ->and($mtbf['value'])->toBeNull()->and($mtbf['insufficient'])->toBeTrue()
        ->and($mix['preventive_pct'])->toBeNull()->and($mix['insufficient'])->toBeTrue()
        ->and($day->compliance()['rate'])->toBeNull();

    // Nessun guasto: MTBF non calcolabile (non "infinito")
    $quiet = new KpiCalculator(KpiPeriod::between('2026-01-01', '2026-01-31'));
    expect($quiet->mtbf()['failures'])->toBe(0)->and($quiet->mtbf()['value'])->toBeNull()
        ->and($quiet->downtime()['count'])->toBe(0);
});

it('andamento: marzo in settimane da lunedì, stesse regole e stessi minimi per intervallo', function () {
    // 1/3 è domenica: intervalli 1/3, 2-8, 9-15, 16-22, 23-29, 30-31
    $s = $this->kpi->series();
    expect($s['unit'])->toBe('week')
        ->and(array_column($s['buckets'], 'from'))->toBe(['2026-03-01', '2026-03-02', '2026-03-09', '2026-03-16', '2026-03-23', '2026-03-30'])
        // Le ore si sommano esattamente al totale del periodo (ogni fermo è tagliato ai bordi)
        ->and(round(array_sum(array_column($s['buckets'], 'downtime_hours')), 1))->toBe(294.5)
        // Nessuna settimana ha 3 fermi conclusi: MTTR mai mostrato, ma i fermi contati sono i 5 del mese
        ->and(array_filter(array_column($s['buckets'], 'mttr'), fn ($v) => $v !== null))->toBe([])
        ->and(array_sum(array_column($s['buckets'], 'mttr_n')))->toBe(5)
        ->and(array_sum(array_column($s['buckets'], 'preventive')))->toBe(4)
        ->and(array_sum(array_column($s['buckets'], 'corrective')))->toBe(3);

    // Un anno intero va per mesi
    expect((new KpiCalculator(KpiPeriod::between('2025-04-01', '2026-03-31')))->series()['unit'])->toBe('month');
});
