<?php

use App\Livewire\Assets\Categories;
use App\Livewire\Assets\Form;
use App\Livewire\Assets\Index;
use App\Livewire\Assets\Locations;
use App\Livewire\Assets\Show;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AuditLog;
use App\Models\Location;
use App\Support\Access\Access;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
});

function makeTree(): array
{
    $site = Location::query()->create(['name' => 'Sito']);
    $area = Location::query()->create(['parent_id' => $site->id, 'name' => 'Reparto']);
    $line = Location::query()->create(['parent_id' => $area->id, 'name' => 'Linea 1']);

    return [$site, $area, $line];
}

it('le ubicazioni hanno tipo, percorso e nome completo', function () {
    [$site, $area, $line] = makeTree();

    expect($site->type->value)->toBe('sito')
        ->and($area->fresh()->type->value)->toBe('area')
        ->and($line->fresh()->type->value)->toBe('linea')
        ->and($line->fresh()->path)->toBe("/{$site->id}/{$area->id}/{$line->id}/")
        ->and($line->fullName())->toBe('Sito › Reparto › Linea 1');
});

it('spostando un\'area si aggiornano anche le linee contenute', function () {
    [$site, $area, $line] = makeTree();
    $other = Location::query()->create(['name' => 'Secondo sito']);

    $area->fill(['parent_id' => $other->id])->save();

    expect($line->fresh()->path)->toBe("/{$other->id}/{$area->id}/{$line->id}/")
        ->and(Location::query()->find($site->id)->path)->toBe("/{$site->id}/");
});

it('il filtro per ubicazione comprende le ubicazioni contenute', function () {
    [$site, $area, $line] = makeTree();
    $inLine = Asset::factory()->create(['location_id' => $line->id]);
    $inArea = Asset::factory()->create(['location_id' => $area->id]);
    $elsewhere = Asset::factory()->create();

    expect(Asset::query()->inLocation($area)->pluck('id')->all())->toEqualCanonicalizing([$inLine->id, $inArea->id])
        ->and(Asset::query()->inLocation($site)->count())->toBe(2);

    Livewire::actingAs($this->admin)->test(Index::class)->set('location', (string) $area->id)
        ->assertSee($inLine->code)->assertSee($inArea->code)->assertDontSee($elsewhere->code);
});

it('dalla pagina si creano ubicazioni, ma non si elimina un\'ubicazione con macchine', function () {
    Livewire::actingAs($this->admin)->test(Locations::class)
        ->call('create')->set('form.name', 'Stabilimento')->call('save')->assertHasNoErrors();
    $site = Location::query()->sole();

    Livewire::actingAs($this->admin)->test(Locations::class)
        ->call('create', $site->id)->set('form.name', 'Reparto presse')->call('save')->assertHasNoErrors();
    expect(Location::query()->where('name', 'Reparto presse')->sole()->type->value)->toBe('area');

    // Nome ripetuto nello stesso punto dell'albero
    Livewire::actingAs($this->admin)->test(Locations::class)
        ->call('create', $site->id)->set('form.name', 'Reparto presse')->call('save')->assertHasErrors('form.name');

    Asset::factory()->create(['location_id' => $site->id]);
    Livewire::actingAs($this->admin)->test(Locations::class)->call('delete', $site->id)->assertDispatched('toast');
    expect(Location::query()->find($site->id))->not->toBeNull();
});

it('crea una macchina dal modulo e la registra nel registro attività', function () {
    [, , $line] = makeTree();
    $category = AssetCategory::query()->create(['name' => 'Presse']);

    Livewire::actingAs($this->admin)->test(Form::class)
        ->set('form.code', 'PRS-01')->set('form.name', 'Pressa idraulica')
        ->set('form.location_id', (string) $line->id)->set('form.asset_category_id', (string) $category->id)
        ->set('form.criticality', 'A')->set('form.year', '2018')->set('form.purchase_cost', '12500.50')
        ->call('save')->assertHasNoErrors()->assertRedirect();

    $asset = Asset::query()->sole();
    expect($asset->criticality->value)->toBe('A')
        ->and($asset->location_id)->toBe($line->id)
        ->and($asset->purchase_cost)->toBe('12500.50')
        ->and(strlen($asset->qr_token))->toBe(24)
        ->and($asset->created_by)->toBe($this->admin->id)
        ->and(AuditLog::query()->where('action', 'assets.created')->exists())->toBeTrue();

    // Codice già usato
    Livewire::actingAs($this->admin)->test(Form::class)
        ->set('form.code', 'PRS-01')->set('form.name', 'Doppione')->call('save')->assertHasErrors('form.code');
});

it('una macchina non può stare dentro un suo sottogruppo', function () {
    $press = Asset::factory()->create();
    $unit = Asset::factory()->create(['parent_id' => $press->id]);

    Livewire::actingAs($this->admin)->test(Form::class, ['asset' => $press])
        ->set('form.parent_id', (string) $unit->id)->call('save')->assertHasErrors('form.parent_id');

    // E non si elimina finché ha sottogruppi
    Livewire::actingAs($this->admin)->test(Show::class, ['asset' => $press])->call('delete')->assertDispatched('toast');
    expect(Asset::query()->find($press->id))->not->toBeNull();
});

it('chi può solo vedere le macchine non può crearle né modificarle', function () {
    $asset = Asset::factory()->create();
    $requester = userWithRole(Access::REQUESTER);

    $this->actingAs($requester)->get(route('assets.index'))->assertOk()->assertDontSee(__('assets.new'));
    $this->actingAs($requester)->get(route('assets.show', $asset))->assertOk()->assertDontSee(__('assets.status.title'));
    $this->actingAs($requester)->get(route('assets.create'))->assertForbidden();
    $this->actingAs($requester)->get(route('assets.edit', $asset))->assertForbidden();
    $this->actingAs($requester)->get(route('assets.import'))->assertForbidden();
    $this->actingAs($requester)->get(route('assets.categories'))->assertForbidden();
    Livewire::actingAs($requester)->test(Show::class, ['asset' => $asset])->call('delete')->assertForbidden();
});

it('il costo d\'acquisto lo vede solo chi può vedere i costi', function () {
    $asset = Asset::factory()->create(['purchase_cost' => 98765.43]);

    $this->actingAs($this->admin)->get(route('assets.show', $asset))->assertSee('98.765,43');
    $this->actingAs(userWithRole(Access::TECHNICIAN))->get(route('assets.show', $asset))->assertOk()->assertDontSee('98.765,43');
});

it('l\'elenco filtra per stato e mostra i conteggi', function () {
    Asset::factory()->count(2)->create();
    $broken = Asset::factory()->create(['status' => 'guasto']);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSee('3')
        ->set('status', 'guasto')->assertSee($broken->code)
        ->assertViewHas('assets', fn ($p) => $p->total() === 1);
});

it('eliminando una categoria le macchine restano senza categoria', function () {
    $category = AssetCategory::query()->create(['name' => 'Compressori']);
    $asset = Asset::factory()->create(['asset_category_id' => $category->id]);

    Livewire::actingAs($this->admin)->test(Categories::class)->call('delete', $category->id);

    expect($asset->fresh()->asset_category_id)->toBeNull();
});

it('i nuovi permessi delle macchine arrivano ai ruoli esistenti con l\'aggiornamento', function () {
    $technician = userWithRole(Access::TECHNICIAN);
    expect($technician->can('assets.status'))->toBeTrue()
        ->and($technician->can('assets.readings'))->toBeTrue()
        ->and($technician->can('assets.manage'))->toBeFalse()
        ->and(userWithRole(Access::REQUESTER)->can('assets.status'))->toBeFalse();
});
