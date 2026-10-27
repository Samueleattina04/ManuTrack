<?php

use App\Livewire\Inventory\Import;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\Access\Access;
use App\Support\Inventory\PartImport;
use App\Support\Inventory\Stock;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->main = makeWarehouse('Magazzino centrale');
});

/** @param list<list<string>> $rows */
function partsPreview(array $rows): array
{
    $import = app(PartImport::class);
    $path = tempnam(sys_get_temp_dir(), 'ric');
    file_put_contents($path, excelCsv($rows));

    return $import->analyze($import->read($path, 'csv'));
}

it('crea i ricambi con giacenza iniziale, fornitore e costo', function () {
    $preview = partsPreview([
        ['Codice', 'Nome', 'Unità', 'Scorta minima', 'Fornitore', 'Codice fornitore', 'Prezzo fornitore', 'Magazzino', 'Giacenza', 'Costo unitario', 'Scaffale'],
        ['CUS-1', 'Cuscinetto', 'pz', '2', 'Fornitore Prova', 'F-1', '7,50', 'Magazzino centrale', '12', '7,20', 'A-1'],
        ['OLIO-1', 'Olio', 'l', '20', '', '', '', '', '', '', ''],
    ]);
    expect($preview['create'])->toBe(2)->and($preview['invalid'])->toBe(0)->and($preview['new_suppliers'])->toBe(['Fornitore Prova']);

    $result = app(PartImport::class)->apply($preview['token'], $this->admin);
    expect($result)->toBe(['created' => 2, 'updated' => 0]);

    $part = Part::query()->where('code', 'CUS-1')->sole();
    expect((float) $part->stock_total)->toBe(12.0)->and((float) $part->unit_cost)->toBe(7.2)
        ->and($part->preferredSupplier()->name)->toBe('Fornitore Prova')
        ->and($part->preferredSupplier()->pivot->supplier_code)->toBe('F-1')
        ->and($part->levels()->sole()->bin)->toBe('A-1')
        ->and(StockMovement::query()->count())->toBe(1)
        ->and((float) Part::query()->where('code', 'OLIO-1')->value('stock_total'))->toBe(0.0);
});

it('aggiorna senza toccare le giacenze esistenti', function () {
    $part = makePart(['code' => 'CUS-1', 'name' => 'Vecchio nome', 'min_stock' => 5]);
    app(Stock::class)->receive($part, $this->main, 4, $this->admin);

    $preview = partsPreview([['codice', 'nome', 'scorta_minima', 'giacenza'], ['CUS-1', 'Nuovo nome', '', '99']]);
    expect($preview['update'])->toBe(1)->and($preview['stock_ignored'])->toBe(1);
    app(PartImport::class)->apply($preview['token'], $this->admin);

    $part->refresh();
    expect($part->name)->toBe('Nuovo nome')->and((float) $part->min_stock)->toBe(5.0)
        ->and((float) $part->stock_total)->toBe(4.0)->and(StockMovement::query()->count())->toBe(1);
});

it('segnala le righe sbagliate e importa le altre', function () {
    $preview = partsPreview([
        ['codice', 'nome', 'giacenza', 'magazzino', 'scorta_minima'],
        ['OK-1', 'Buono', '', '', ''],
        ['', 'Senza codice', '', '', ''],
        ['BAD-2', 'Magazzino inesistente', '3', 'Inventato', ''],
        ['BAD-3', 'Giacenza negativa', '-1', '', ''],
        ['BAD-4', 'Numero sbagliato', '', '', 'tanti'],
        ['OK-1', 'Doppione', '', '', ''],
    ]);
    expect($preview['create'])->toBe(1)->and($preview['invalid'])->toBe(5)
        ->and(array_column($preview['errors'], 'line'))->toBe([3, 4, 5, 6, 7]);

    app(PartImport::class)->apply($preview['token'], $this->admin);
    expect(Part::query()->pluck('code')->all())->toBe(['OK-1']);
});

it('legge anche i file Excel', function () {
    $path = tempnam(sys_get_temp_dir(), 'ric').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Codice', 'Nome', 'Giacenza', 'Costo unitario']));
    $writer->addRow(Row::fromValues(['XL-1', 'Da Excel', 3, 4.25]));
    $writer->close();

    $import = app(PartImport::class);
    $import->apply($import->analyze($import->read($path, 'xlsx'))['token'], $this->admin);

    $part = Part::query()->sole();
    expect((float) $part->stock_total)->toBe(3.0)->and((float) $part->unit_cost)->toBe(4.25);
});

it('la pagina guida dall\'anteprima alla conferma e il modello si scarica', function () {
    $csv = UploadedFile::fake()->createWithContent('ricambi.csv', excelCsv([['codice', 'nome', 'fornitore'], ['A-1', 'Primo', 'Fornitore Nuovo'], ['A-2', 'Secondo', '']]));

    $page = Livewire::actingAs($this->admin)->test(Import::class)->set('file', $csv)->assertSet('preview.create', 2);
    expect(Part::query()->count())->toBe(0);
    $page->call('confirm')->assertSet('result', ['created' => 2, 'updated' => 0]);
    expect(Part::query()->count())->toBe(2)->and(Supplier::query()->count())->toBe(1);

    $this->actingAs($this->admin)->get(route('inventory.import.template'))->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertSee('codice;nome', false);
});

it('chi non gestisce il magazzino non può importare', function () {
    $tech = userWithRole(Access::TECHNICIAN);
    $this->actingAs($tech)->get(route('inventory.import'))->assertForbidden();
});
