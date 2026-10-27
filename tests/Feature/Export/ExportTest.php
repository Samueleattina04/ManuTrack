<?php

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\WorkOrder;
use App\Support\Access\Access;
use App\Support\Export\SafeCell;
use App\Support\Inventory\Stock;
use OpenSpout\Reader\XLSX\Reader;

beforeEach(function () {
    $this->admin = installManuTrack();
});

/** Righe di un CSV esportato (senza BOM, separatore ";") */
function csvRows(string $content): array
{
    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF");
    $h = fopen('php://memory', 'r+');
    fwrite($h, substr($content, 3));
    rewind($h);
    $rows = [];
    while (($r = fgetcsv($h, null, ';', '"', '')) !== false) {
        $rows[] = $r;
    }

    return $rows;
}

/** Fogli di un .xlsx esportato: nome => righe */
function xlsxSheets(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $out = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $out[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();

    return $out;
}

it('neutralizza i testi che sarebbero formule', function (string $in, string $out) {
    expect(SafeCell::text($in))->toBe($out);
})->with([
    ['=1+1', "'=1+1"],
    ['+39 333', "'+39 333"],
    ['-2+3', "'-2+3"],
    ['@SUM(A1)', "'@SUM(A1)"],
    ["\t=1", "'\t=1"],
    ["\r=1", "'\r=1"],
    ['  =cmd|\'/c calc\'!A0', "'  =cmd|'/c calc'!A0"],
    ['＝1+1', "'＝1+1"],
    ['Pompa = 3 bar', 'Pompa = 3 bar'],
    ['PRS-01', 'PRS-01'],
    ['', ''],
]);

it('ordini in CSV: filtri, formule neutralizzate, costi solo con il permesso', function () {
    $tech = userWithRole(Access::TECHNICIAN);
    $danger = makeOrder($this->admin, [$tech->id], ['title' => '=HYPERLINK("http://esempio.invalid","clic")']);
    $danger->forceFill(['external_cost' => '12.50'])->save();
    $other = makeOrder($this->admin, [], ['title' => 'Controllo cinghia', 'priority' => 'alta']);

    $res = $this->actingAs($this->admin)->get(route('export.work-orders', ['format' => 'csv', 'vista' => 'tutti', 'miei' => '0']));
    $res->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($res->headers->get('Content-Disposition'))->toContain('manutrack-ordini-');
    $rows = csvRows($res->streamedContent());
    expect($rows[0])->toContain(__('export.col.total_cost'))
        ->and(count($rows))->toBe(3);
    $byNumber = collect($rows)->slice(1)->keyBy(0);
    expect($byNumber[$danger->number][2])->toBe('\'=HYPERLINK("http://esempio.invalid","clic")')
        ->and(end($rows[array_search($danger->number, array_column($rows, 0))]))->toBe('12,50');

    // Filtro priorità
    $rows = csvRows($this->actingAs($this->admin)->get(route('export.work-orders', ['format' => 'csv', 'vista' => 'tutti', 'miei' => '0', 'priorita' => 'alta']))->streamedContent());
    expect(array_column(array_slice($rows, 1), 0))->toBe([$other->number]);

    // Tecnico con il permesso di esportare: niente costi, e parte dai suoi ordini come la pagina
    $tech->givePermissionTo('reports.export');
    $rows = csvRows($this->actingAs($tech)->get(route('export.work-orders', ['format' => 'csv', 'vista' => 'tutti']))->streamedContent());
    expect($rows[0])->not->toContain(__('export.col.total_cost'))
        ->and($rows[0])->not->toContain(__('export.col.labor_cost'))
        ->and(array_column(array_slice($rows, 1), 0))->toBe([$danger->number]);
});

it('macchine in Excel: filtri, celle di testo neutralizzate, costo d\'acquisto con il permesso', function () {
    Asset::factory()->create(['code' => 'A-1', 'name' => '@SOMMA(1;2)', 'status' => 'ferma', 'purchase_cost' => '1500']);
    Asset::factory()->create(['code' => 'A-2', 'name' => 'Pressa', 'status' => 'operativa']);

    $res = $this->actingAs($this->admin)->get(route('export.assets', ['format' => 'xlsx', 'stato' => 'ferma']));
    $res->assertOk();
    $sheets = xlsxSheets($res->getFile()->getPathname());
    $rows = $sheets[__('export.files.macchine')];
    expect($rows)->toHaveCount(2)
        ->and($rows[1][0])->toBe('A-1')
        ->and($rows[1][1])->toBe("'@SOMMA(1;2)")
        ->and(end($rows[1]))->toEqual(1500)
        ->and($rows[0])->toContain(__('export.col.purchase_cost'));

    // L'osservatore vede i costi, il magazziniere (con l'esportazione concessa) no
    $keeper = userWithRole(Access::STOREKEEPER);
    $keeper->givePermissionTo('reports.export');
    $rows = csvRows($this->actingAs($keeper)->get(route('export.assets', ['format' => 'csv']))->streamedContent());
    expect($rows[0])->not->toContain(__('export.col.purchase_cost'))->and($rows)->toHaveCount(3);
});

it('ricambi: filtro sotto scorta, giacenze e valore solo a chi vede i costi', function () {
    $main = makeWarehouse();
    $low = makePart(['code' => 'R-LOW', 'min_stock' => 5]);
    $ok = makePart(['code' => 'R-OK', 'min_stock' => 1]);
    app(Stock::class)->receive($low, $main, 2, $this->admin, 10);
    app(Stock::class)->receive($ok, $main, 3.5, $this->admin, 4);

    $rows = csvRows($this->actingAs($this->admin)->get(route('export.parts', ['format' => 'csv', 'scorta' => 'sotto']))->streamedContent());
    expect(array_column(array_slice($rows, 1), 0))->toBe(['R-LOW'])
        ->and($rows[1][10])->toBe('Magazzino centrale: 2')
        ->and(array_slice($rows[1], -2))->toBe(['10,00', '20,00']);

    $rows = csvRows($this->actingAs($this->admin)->get(route('export.parts', ['format' => 'csv']))->streamedContent());
    expect(collect($rows)->firstWhere(0, 'R-OK')[6])->toBe('3,5');

    $tech = userWithRole(Access::TECHNICIAN);
    $tech->givePermissionTo('reports.export');
    $rows = csvRows($this->actingAs($tech)->get(route('export.parts', ['format' => 'csv']))->streamedContent());
    expect($rows[0])->not->toContain(__('export.col.unit_cost'))->not->toContain(__('export.col.stock_value'));
});

it('permessi: serve "Esportare" più il permesso di vedere l\'elenco', function () {
    $requester = userWithRole(Access::REQUESTER);
    $tech = userWithRole(Access::TECHNICIAN);
    $keeper = userWithRole(Access::STOREKEEPER);
    foreach (['export.work-orders', 'export.assets', 'export.parts', 'export.kpi'] as $route) {
        $this->actingAs($requester)->get(route($route, ['format' => 'csv']))->assertForbidden();
        $this->actingAs($tech)->get(route($route, ['format' => 'csv']))->assertForbidden();
        $this->actingAs($keeper)->get(route($route, ['format' => 'csv']))->assertForbidden();
    }
    // Esportare senza vedere gli indicatori non basta
    $tech->givePermissionTo('reports.export');
    $this->actingAs($tech)->get(route('export.kpi', ['format' => 'csv']))->assertForbidden();
    $this->actingAs($tech)->get(route('export.assets', ['format' => 'csv']))->assertOk();

    $this->actingAs(userWithRole(Access::VIEWER))->get(route('export.parts', ['format' => 'csv']))->assertOk();
    auth()->logout();
    $this->get(route('export.assets', ['format' => 'csv']))->assertRedirect(route('login'));
    $this->get('/esporta/macchine.pdf')->assertNotFound();
});

it('indicatori: riepilogo con "dati insufficienti", Excel con un foglio per parte, costi con il permesso', function () {
    $this->travelTo('2026-04-10 12:00:00');
    $rows = csvRows($this->actingAs($this->admin)->get(route('export.kpi', ['format' => 'csv', 'periodo' => 'mese_prec']))->streamedContent());
    $summary = collect($rows)->keyBy(0);
    expect($summary[__('export.kpi.period')][4])->toBe('01/03/2026 – 31/03/2026 (Europe/Rome)')
        ->and($summary[__('export.kpi.mttr')][1])->toBe('')
        ->and($summary[__('export.kpi.mttr')][4])->toBe(__('export.kpi.insufficient'))
        ->and($summary[__('export.kpi.downtime_count')][1])->toBe('0')
        ->and($summary)->toHaveKey(__('export.kpi.cost_total'));

    $res = $this->actingAs($this->admin)->get(route('export.kpi', ['format' => 'xlsx', 'periodo' => 'scelta', 'dal' => '2026-03-01', 'al' => '2026-03-31']));
    expect(array_keys(xlsxSheets($res->getFile()->getPathname())))->toBe(array_values(__('export.kpi_parts')))
        ->and($res->headers->get('Content-Disposition'))->toContain('manutrack-indicatori-20260301-20260331-');

    $this->actingAs($this->admin)->get(route('export.kpi', ['format' => 'csv', 'parte' => 'andamento']))->assertOk();
    $this->actingAs($this->admin)->get(route('export.kpi', ['format' => 'csv', 'parte' => 'altro']))->assertNotFound();
    $this->actingAs($this->admin)->get(route('export.kpi', ['format' => 'csv', 'periodo' => 'scelta', 'dal' => '2026-05-01', 'al' => '2026-05-02']))->assertStatus(422);

    // Senza il permesso dei costi: niente foglio dei costi né totale
    $keeper = userWithRole(Access::STOREKEEPER);
    $keeper->givePermissionTo('reports.export');
    $res = $this->actingAs($keeper)->get(route('export.kpi', ['format' => 'xlsx']));
    $sheets = xlsxSheets($res->getFile()->getPathname());
    expect($sheets)->not->toHaveKey(__('export.kpi_parts.costi'))
        ->and(array_column($sheets[__('export.kpi_parts.riepilogo')], 0))->not->toContain(__('export.kpi.cost_total'));
    $this->actingAs($keeper)->get(route('export.kpi', ['format' => 'csv', 'parte' => 'costi']))->assertNotFound();
});

it('scrive l\'esportazione nel registro attività e mostra il pulsante solo a chi può', function () {
    makeOrder($this->admin);
    $this->actingAs($this->admin)->get(route('export.work-orders', ['format' => 'csv', 'q' => 'guarnizione']))->assertOk();
    $log = AuditLog::query()->where('action', 'reports.exported')->first();
    expect($log->description)->toBe('ordini · csv')->and($log->changes['filtri']['q'])->toBe('guarnizione');

    $this->actingAs($this->admin)->get(route('work-orders.index'))->assertSee(route('export.work-orders', ['format' => 'csv', 'vista' => 'da_fare', 'miei' => '0']));
    $this->actingAs(userWithRole(Access::TECHNICIAN))->get(route('assets.index'))->assertDontSee(__('export.button'));
    $this->actingAs(userWithRole(Access::VIEWER))->get(route('inventory.parts.index'))->assertSee(__('export.button'));
});

it('le date in Excel sono date e i numeri numeri', function () {
    $this->travelTo('2026-03-15 10:30:00');
    $wo = makeOrder($this->admin);
    $res = $this->actingAs($this->admin)->get(route('export.work-orders', ['format' => 'xlsx', 'vista' => 'tutti']));
    $row = xlsxSheets($res->getFile()->getPathname())[__('export.files.ordini')][1];
    expect($row[0])->toBe($wo->number)
        ->and($row[10])->toBeInstanceOf(DateTimeInterface::class)
        ->and($row[10]->format('Y-m-d H:i'))->toBe('2026-03-15 10:30')
        ->and($row[15])->toBe(0);
    expect(WorkOrder::query()->count())->toBe(1);
});
