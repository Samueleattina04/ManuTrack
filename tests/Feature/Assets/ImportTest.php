<?php

use App\Livewire\Assets\Import;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AuditLog;
use App\Models\Location;
use App\Support\Access\Access;
use App\Support\Assets\AssetImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    $this->admin = installManuTrack();
});

function importRows(array $rows): array
{
    $import = app(AssetImport::class);
    $path = tempnam(sys_get_temp_dir(), 'imp');
    file_put_contents($path, excelCsv($rows));

    return $import->analyze($import->read($path, 'csv'));
}

it('importa macchine da un CSV di Excel creando ubicazioni, categorie e legami', function () {
    $preview = importRows([
        ['Codice', 'Nome', 'Categoria', 'Ubicazione', 'Macchina padre', 'Criticità', 'Stato', 'Data installazione', 'Costo acquisto', 'Colonna strana'],
        ['PRS-01-CEN', 'Centralina', 'Centraline', 'Sito > Reparto presse > Linea 1', 'PRS-01', 'b', '', '01/02/2020', '', 'x'],
        ['PRS-01', 'Pressa più grande', 'Presse', 'Sito > Reparto presse > Linea 1', '', 'A', 'In guasto', '15/03/19', '12.500,50', ''],
    ]);

    expect($preview['create'])->toBe(2)->and($preview['invalid'])->toBe(0)
        ->and($preview['new_locations'])->toBe(['Sito › Reparto presse › Linea 1'])
        ->and($preview['new_categories'])->toEqualCanonicalizing(['Centraline', 'Presse'])
        ->and($preview['unknown_columns'])->toBe(['Colonna strana']);

    $result = app(AssetImport::class)->apply($preview['token'], $this->admin);
    expect($result)->toBe(['created' => 2, 'updated' => 0]);

    $press = Asset::query()->where('code', 'PRS-01')->sole();
    $unit = Asset::query()->where('code', 'PRS-01-CEN')->sole();
    expect($press->name)->toBe('Pressa più grande')
        ->and($press->status->value)->toBe('guasto')
        ->and($press->criticality->value)->toBe('A')
        ->and($press->installed_on->toDateString())->toBe('2019-03-15')
        ->and($press->purchase_cost)->toBe('12500.50')
        ->and($unit->parent_id)->toBe($press->id)
        ->and($unit->criticality->value)->toBe('B')
        ->and($unit->status->value)->toBe('operativa')
        ->and($press->location->fullName())->toBe('Sito › Reparto presse › Linea 1')
        ->and(Location::query()->count())->toBe(3)
        ->and(AssetCategory::query()->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'assets.imported')->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists('imports/'.$preview['token'].'.json'))->toBeFalse();
});

it('aggiorna le macchine esistenti senza cancellare i dati con le celle vuote', function () {
    Asset::factory()->create(['code' => 'CMP-01', 'name' => 'Compressore', 'manufacturer' => 'Costruttore Alfa', 'criticality' => 'A']);

    $preview = importRows([
        ['codice', 'nome', 'costruttore', 'modello', 'criticita'],
        ['cmp-01', '', '', 'CV-37', ''],
    ]);
    expect($preview['update'])->toBe(1)->and($preview['create'])->toBe(0);
    app(AssetImport::class)->apply($preview['token'], $this->admin);

    $asset = Asset::query()->sole();
    expect($asset->name)->toBe('Compressore')
        ->and($asset->manufacturer)->toBe('Costruttore Alfa')
        ->and($asset->model)->toBe('CV-37')
        ->and($asset->criticality->value)->toBe('A');
});

it('segnala le righe sbagliate con il numero di riga e importa le altre', function () {
    $preview = importRows([
        ['codice', 'nome', 'stato', 'criticita', 'anno', 'data_installazione', 'macchina_padre'],
        ['OK-1', 'Buona', '', '', '2015', '', ''],
        ['', 'Senza codice', '', '', '', '', ''],
        ['OK-1', 'Doppione', '', '', '', '', ''],
        ['BAD-2', 'Stato strano', 'rotta', 'Z', 'vecchia', '31/02/2020', ''],
        ['BAD-3', 'Padre inesistente', '', '', '', '', 'NON-ESISTE'],
    ]);

    expect($preview['create'])->toBe(1)->and($preview['invalid'])->toBe(4)
        ->and(array_column($preview['errors'], 'line'))->toBe([3, 4, 5, 6]);
    $bad = collect($preview['errors'])->firstWhere('line', 5)['messages'];
    expect(implode(' ', $bad))->toContain('rotta')->toContain('"Z"')->toContain('31/02/2020');

    app(AssetImport::class)->apply($preview['token'], $this->admin);
    expect(Asset::query()->pluck('code')->all())->toBe(['OK-1']);
});

it('legge anche i file Excel', function () {
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Codice', 'Nome', 'Anno', 'Data installazione']));
    $writer->addRow(Row::fromValues(['XL-01', 'Da Excel', 2019, new DateTimeImmutable('2020-05-04')]));
    $writer->close();

    $import = app(AssetImport::class);
    $preview = $import->analyze($import->read($path, 'xlsx'));
    $import->apply($preview['token'], $this->admin);

    $asset = Asset::query()->sole();
    expect($asset->code)->toBe('XL-01')->and($asset->year)->toBe(2019)->and($asset->installed_on->toDateString())->toBe('2020-05-04');
});

it('la pagina guida dall\'anteprima alla conferma', function () {
    $csv = UploadedFile::fake()->createWithContent('macchine.csv', excelCsv([['codice', 'nome'], ['A-1', 'Prima'], ['A-2', 'Seconda']]));

    $page = Livewire::actingAs($this->admin)->test(Import::class)->set('file', $csv)
        ->assertSee(__('assets.import.preview_title'))
        ->assertSet('preview.create', 2);
    expect(Asset::query()->count())->toBe(0);

    $page->call('confirm')->assertSet('result', ['created' => 2, 'updated' => 0]);
    expect(Asset::query()->count())->toBe(2);

    // Senza le colonne obbligatorie
    Livewire::actingAs($this->admin)->test(Import::class)
        ->set('file', UploadedFile::fake()->createWithContent('x.csv', "foo;bar\n1;2\n"))->assertHasErrors('file');
});

it('solo chi gestisce le macchine può importare e scaricare il modello', function () {
    $this->actingAs(userWithRole(Access::TECHNICIAN))->get(route('assets.import.template'))->assertForbidden();
    $response = $this->actingAs($this->admin)->get(route('assets.import.template'))->assertOk();
    expect($response->getContent())->toStartWith("\xEF\xBB\xBFcodice;nome;");
});
