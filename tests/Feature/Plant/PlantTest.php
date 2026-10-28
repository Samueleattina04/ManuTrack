<?php

use App\Livewire\Plant\Editor;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AuditLog;
use App\Models\LayoutItem;
use App\Models\Location;
use App\Models\PlantLayout;
use App\Support\Access\Access;
use App\Support\Plant\AssetModel;
use App\Support\Plant\LayoutEditor;
use App\Support\Plant\PlantData;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->actingAs($this->admin);
    $this->editor = app(LayoutEditor::class);
});

function plantLayout(array $attrs = []): PlantLayout
{
    return app(LayoutEditor::class)->saveLayout(null, array_merge(['name' => 'Reparto prova', 'location_id' => null, 'width' => 20, 'depth' => 10], $attrs));
}

it('permessi: tutti quelli che vedono le macchine vedono la pianta, solo chi le gestisce la modifica', function () {
    $layout = plantLayout();
    foreach ([Access::MANAGER, Access::TECHNICIAN, Access::REQUESTER, Access::VIEWER] as $role) {
        $user = userWithRole($role);
        $this->actingAs($user)->get(route('plant.index'))->assertOk()->assertSee('Reparto prova');
        $this->actingAs($user)->getJson(route('plant.status', $layout))->assertOk();
        $canManage = $user->can('assets.manage');
        $this->actingAs($user)->get(route('plant.edit', $layout))->assertStatus($canManage ? 200 : 403);
        $this->actingAs($user)->get(route('plant.create'))->assertStatus($canManage ? 200 : 403);
    }
    auth()->logout();
    $this->get(route('plant.index'))->assertRedirect(route('login'));
});

it('pianta vuota: invito a crearla solo per chi può', function () {
    $this->get(route('plant.index'))->assertOk()->assertSee(__('plant.empty_title'))->assertSee(route('plant.create'));
    $this->actingAs(userWithRole(Access::TECHNICIAN))->get(route('plant.index'))->assertOk()->assertSee(__('plant.empty_viewer'))->assertDontSee(route('plant.create'));
});

it('misure del pavimento entro i limiti', function () {
    expect(fn () => plantLayout(['width' => 1]))->toThrow(ValidationException::class)
        ->and(fn () => plantLayout(['depth' => 501]))->toThrow(ValidationException::class);
    expect((float) plantLayout(['width' => 500, 'depth' => 2])->width)->toBe(500.0);
});

it('aggiunge le macchine in posti liberi, con le misure della forma, senza sovrapporle', function () {
    $press = AssetCategory::query()->create(['name' => 'Presse', 'shape' => 'press']);
    $layout = plantLayout();
    $items = collect(range(1, 6))->map(fn () => $this->editor->addAsset($layout, Asset::factory()->create(['asset_category_id' => $press->id])));
    expect((float) $items[0]->w)->toBe(2.4)->and((float) $items[0]->d)->toBe(2.0)->and((float) $items[0]->h)->toBe(3.0);
    // Nessuna sovrapposizione e tutto dentro il pavimento
    foreach ($items as $i => $a) {
        expect((float) $a->x - $a->w / 2)->toBeGreaterThanOrEqual(0)->and((float) $a->x + $a->w / 2)->toBeLessThanOrEqual(20)
            ->and((float) $a->z - $a->d / 2)->toBeGreaterThanOrEqual(0)->and((float) $a->z + $a->d / 2)->toBeLessThanOrEqual(10);
        foreach ($items as $j => $b) {
            if ($i < $j) {
                $overlap = abs($a->x - $b->x) < ($a->w + $b->w) / 2 && abs($a->z - $b->z) < ($a->d + $b->d) / 2;
                expect($overlap)->toBeFalse();
            }
        }
    }
    expect(AuditLog::query()->where('action', 'plant.item_added')->count())->toBe(6);
});

it('una macchina sta in una sola pianta; solo macchine dell\'ubicazione e non dismesse', function () {
    $site = Location::query()->create(['name' => 'Sito A']);
    $line = Location::query()->create(['name' => 'Linea 1', 'parent_id' => $site->id]);
    $other = Location::query()->create(['name' => 'Sito B']);
    $layoutA = plantLayout(['location_id' => $site->id]);
    $layoutAll = plantLayout(['name' => 'Tutto']);

    $inLine = Asset::factory()->create(['location_id' => $line->id]);
    $elsewhere = Asset::factory()->create(['location_id' => $other->id]);
    $retired = Asset::factory()->create(['location_id' => $site->id, 'status' => 'dismessa']);

    $this->editor->addAsset($layoutA, $inLine);
    expect(fn () => $this->editor->addAsset($layoutAll, $inLine))->toThrow(ValidationException::class, __('plant.errors.already_placed', ['code' => $inLine->code, 'layout' => 'Reparto prova']))
        ->and(fn () => $this->editor->addAsset($layoutA, $elsewhere))->toThrow(ValidationException::class)
        ->and(fn () => $this->editor->addAsset($layoutA, $retired))->toThrow(ValidationException::class);
    $this->editor->addAsset($layoutAll, $elsewhere);
    expect(LayoutItem::query()->count())->toBe(2);
});

it('la macchina va nella zona della sua ubicazione', function () {
    $site = Location::query()->create(['name' => 'Sito']);
    $line = Location::query()->create(['name' => 'Linea 2', 'parent_id' => $site->id]);
    $layout = plantLayout(['width' => 40, 'depth' => 20, 'location_id' => $site->id]);
    $this->editor->addZone($layout, null, 'Magazzino');
    $zone = $this->editor->addZone($layout, $line);
    expect($zone->name())->toBe('Linea 2');

    $item = $this->editor->addAsset($layout, Asset::factory()->create(['location_id' => $line->id]));
    expect(abs($item->x - $zone->x))->toBeLessThanOrEqual(($zone->w - $item->w) / 2)
        ->and(abs($item->z - $zone->z))->toBeLessThanOrEqual(($zone->d - $item->d) / 2);
    expect(fn () => $this->editor->addZone($layout, null, '  '))->toThrow(ValidationException::class);
});

it('spostamenti sulla griglia e dentro il pavimento, anche ruotati', function () {
    $conveyor = AssetCategory::query()->create(['name' => 'Nastri', 'shape' => 'conveyor']);
    $layout = plantLayout();
    $item = $this->editor->addAsset($layout, Asset::factory()->create(['asset_category_id' => $conveyor->id])); // 6 × 1

    $this->editor->move($item, 7.13, 3.38);
    expect((float) $item->x)->toBe(7.25)->and((float) $item->z)->toBe(3.5);

    // Fuori dal pavimento: si ferma al bordo
    $this->editor->move($item, -10, 99);
    expect((float) $item->x)->toBe(3.0)->and((float) $item->z)->toBe(9.5);

    // Ruotato di 90°: l'ingombro diventa 1 × 6 e rientra
    $this->editor->update($item, ['rotation' => 90]);
    expect($item->rotation)->toBe(90)->and((float) $item->x)->toBe(3.0)->and((float) $item->z)->toBe(7.0);
    $this->editor->update($item, ['rotation' => -90]);
    expect($item->rotation)->toBe(270);

    expect(fn () => $this->editor->update($item, ['w' => 0.1]))->toThrow(ValidationException::class)
        ->and(fn () => $this->editor->update($item, ['d' => 11]))->toThrow(ValidationException::class)
        ->and(fn () => $this->editor->update($item, ['h' => 31]))->toThrow(ValidationException::class);
});

it('pavimento rimpicciolito: gli elementi rientrano', function () {
    $layout = plantLayout(['width' => 40, 'depth' => 20]);
    $item = $this->editor->addAsset($layout, Asset::factory()->create(), 35, 15);
    $this->editor->saveLayout($layout, ['name' => 'Reparto prova', 'location_id' => null, 'width' => 10, 'depth' => 8]);
    $item->refresh();
    expect((float) $item->x)->toBe(9.0)->and((float) $item->z)->toBe(7.0);
});

it('dati della vista: stato, ordini aperti e collegamenti secondo i permessi', function () {
    $layout = plantLayout();
    $broken = Asset::factory()->create(['status' => 'guasto']);
    $ok = Asset::factory()->create();
    $this->editor->addAsset($layout, $broken);
    $this->editor->addAsset($layout, $ok);
    makeOrder($this->admin, [], ['asset_id' => $broken->id]);

    $data = PlantData::for($layout, $this->admin);
    $byCode = collect($data['items'])->keyBy('code');
    expect($byCode[$broken->code]['tone'])->toBe('ko')
        ->and($byCode[$broken->code]['open_orders'])->toBe(1)
        ->and($byCode[$ok->code]['open_orders'])->toBe(0)
        ->and($byCode[$ok->code]['orders_url'])->not->toBeNull()
        ->and($data['counts'])->toBe(['guasto' => 1, 'operativa' => 1]);

    $requester = userWithRole(Access::REQUESTER);
    $item = collect(PlantData::for($layout, $requester)['items'])->firstWhere('code', $broken->code);
    expect($item['open_orders'])->toBeNull()->and($item['orders_url'])->toBeNull()->and($item['report_url'])->not->toBeNull();

    // Aggiornamento periodico degli stati
    $ok->forceFill(['status' => 'manutenzione'])->save();
    $this->getJson(route('plant.status', $layout))->assertOk()->assertJsonPath($ok->id.'.tone', 'mnt')->assertJsonPath($broken->id.'.open_orders', 1);
});

it('pagina: vista 2D dal server, accessibile, senza Three.js nell\'HTML iniziale', function () {
    $layout = plantLayout();
    $asset = Asset::factory()->create(['code' => 'PIANTA-01', 'status' => 'guasto']);
    $this->editor->addAsset($layout, $asset);
    $this->editor->addZone($layout, null, 'Area spedizioni');

    $html = $this->get(route('plant.index', ['pianta' => $layout->id]))->assertOk()
        ->assertSee('data-plant-asset="'.$asset->id.'"', false)
        ->assertSee('plant-item tone-ko', false)
        ->assertSee('aria-label="PIANTA-01 '.$asset->name.': '.__('enums.asset_status.guasto').'"', false)
        ->assertSee('Area spedizioni')
        ->assertSee('plantView(', false)
        ->getContent();
    expect($html)->not->toMatch('/three-[\w-]+\.js/')->not->toMatch('/plant3d-[\w-]+\.js/');
});

it('editor Livewire: crea, sposta, ruota, toglie, elimina la pianta senza toccare le macchine', function () {
    $asset = Asset::factory()->create();
    $page = Livewire::test(Editor::class)
        ->set('form.name', 'Capannone')->set('form.width', '30')->set('form.depth', '15')
        ->call('saveLayout');
    $layout = PlantLayout::query()->where('name', 'Capannone')->firstOrFail();
    $page->assertRedirect(route('plant.edit', $layout));

    $page = Livewire::test(Editor::class, ['plant' => $layout])
        ->set('addAsset', (string) $asset->id)->call('addAssetToLayout');
    $item = LayoutItem::query()->where('asset_id', $asset->id)->firstOrFail();
    $page->assertSet('selected', $item->id)
        ->call('move', $item->id, 12.1, 5.9)
        ->call('rotate', $item->id, 90)
        ->set('item.w', '3')->set('item.d', '0.1')->call('saveItem')->assertHasErrors('item.d');
    $item->refresh();
    expect((float) $item->x)->toBe(12.0)->and((float) $item->z)->toBe(6.0)->and($item->rotation)->toBe(90);

    $page->set('form.width', '1')->call('saveLayout')->assertHasErrors('form.width');
    $page->call('removeItem', $item->id);
    expect(LayoutItem::query()->count())->toBe(0);

    Livewire::test(Editor::class, ['plant' => $layout])->call('deleteLayout')->assertRedirect(route('plant.index'));
    expect(PlantLayout::query()->count())->toBe(0)->and(Asset::query()->whereKey($asset->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'plant.layout_deleted')->exists())->toBeTrue();

    // Un elemento di un'altra pianta non si tocca da qui
    $other = plantLayout();
    $foreign = $this->editor->addAsset($other, Asset::factory()->create());
    expect(fn () => Livewire::test(Editor::class, ['plant' => plantLayout(['name' => 'Altra'])])->call('move', $foreign->id, 1, 1))
        ->toThrow(ModelNotFoundException::class);
    expect((float) $foreign->refresh()->x)->not->toBe(1.0);

    Livewire::actingAs(userWithRole(Access::TECHNICIAN))->test(Editor::class, ['plant' => $other])->assertForbidden();
});

it('eliminando una macchina sparisce anche dalla pianta', function () {
    $layout = plantLayout();
    $asset = Asset::factory()->create();
    $this->editor->addAsset($layout, $asset);
    $asset->delete();
    expect($layout->items()->count())->toBe(0);
});

it('modello GLB: carica, servi, sostituisci, elimina; solo file glTF 2.0 validi', function () {
    Storage::fake('local');
    $asset = Asset::factory()->create();
    $glb = UploadedFile::fake()->createWithContent('pressa.glb', tinyGlb());

    $this->post(route('assets.model.upload', $asset), ['model' => $glb])->assertRedirect(route('assets.show', $asset))->assertSessionHas('status', __('plant.model.uploaded'));
    $asset->refresh();
    expect($asset->model_name)->toBe('pressa.glb')->and($asset->model_size)->toBe(strlen(tinyGlb()));
    Storage::disk('local')->assertExists($asset->model_path);
    $first = $asset->model_path;

    $res = $this->actingAs(userWithRole(Access::REQUESTER))->get(route('assets.model', $asset));
    $res->assertOk()->assertHeader('Content-Type', 'model/gltf-binary')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($res->streamedContent())->toBe(tinyGlb());

    // Non validi: immagine rinominata, glTF 1, lunghezza sbagliata
    $this->actingAs($this->admin);
    foreach ([
        UploadedFile::fake()->image('finto.glb'),
        UploadedFile::fake()->createWithContent('v1.glb', substr_replace(tinyGlb(), pack('V', 1), 4, 4)),
        UploadedFile::fake()->createWithContent('tagliato.glb', substr(tinyGlb(), 0, -4)),
    ] as $bad) {
        $this->post(route('assets.model.upload', $asset), ['model' => $bad])->assertSessionHasErrors(['model' => __('plant.model.invalid')]);
    }
    config(['manutrack.models.max_mb' => 0]);
    $this->post(route('assets.model.upload', $asset), ['model' => UploadedFile::fake()->createWithContent('x.glb', tinyGlb())])->assertSessionHasErrors('model');
    config(['manutrack.models.max_mb' => 20]);

    // Sostituzione: il vecchio file va via
    $this->post(route('assets.model.upload', $asset), ['model' => UploadedFile::fake()->createWithContent('nuovo.glb', tinyGlb())])->assertRedirect();
    Storage::disk('local')->assertMissing($first);

    // Il tecnico non carica né elimina
    $tech = userWithRole(Access::TECHNICIAN);
    $this->actingAs($tech)->post(route('assets.model.upload', $asset), ['model' => UploadedFile::fake()->createWithContent('t.glb', tinyGlb())])->assertForbidden();
    $this->actingAs($tech)->delete(route('assets.model.delete', $asset))->assertForbidden();

    $path = $asset->refresh()->model_path;
    $this->actingAs($this->admin)->delete(route('assets.model.delete', $asset))->assertRedirect();
    Storage::disk('local')->assertMissing($path);
    expect($asset->refresh()->model_path)->toBeNull();
    $this->get(route('assets.model', $asset))->assertNotFound();
    expect(AuditLog::query()->where('action', 'assets.model_uploaded')->count())->toBe(2);
});

it('scheda macchina: anteprima 3D su richiesta, caricamento solo per chi gestisce', function () {
    $asset = Asset::factory()->create();
    $this->get(route('assets.show', $asset))->assertOk()->assertSee(__('plant.model.show'))->assertSee(__('plant.model.upload'));
    $this->actingAs(userWithRole(Access::TECHNICIAN))->get(route('assets.show', $asset))->assertOk()->assertSee(__('plant.model.show'))->assertDontSee(__('plant.model.upload'));
});

it('il 3D resta fuori dalla cache dell\'app installata', function () {
    $sw = $this->get(route('pwa.worker'))->assertOk()->getContent();
    expect($sw)->toContain('assets\\/app-')
        ->not->toMatch('/three-[\w-]+\.js/')
        ->not->toMatch('/plant3d-[\w-]+\.js/')
        ->not->toMatch('/preview3d-[\w-]+\.js/')
        ->not->toMatch('/logo3d-[\w-]+\.js/')
        ->not->toMatch('/chart-[\w-]+\.js/');
});

it('modello GLB: rifiuta i riferimenti a file esterni, accetta i dati incorporati', function () {
    Storage::fake('local');
    $asset = Asset::factory()->create();
    $external = [
        'buffer https' => ['buffers' => [['byteLength' => 4, 'uri' => 'https://esempio.invalid/dati.bin']]],
        'immagine https' => ['images' => [['uri' => 'https://esempio.invalid/texture.png']]],
        'immagine relativa' => ['images' => [['uri' => 'texture.png']]],
        'buffer relativo' => ['buffers' => [['byteLength' => 4, 'uri' => '../segreti.bin']]],
        'protocollo file' => ['images' => [['uri' => 'file:///etc/passwd']]],
    ];
    foreach ($external as $case => $extra) {
        $this->post(route('assets.model.upload', $asset), ['model' => UploadedFile::fake()->createWithContent('esterno.glb', tinyGlb($extra))])
            ->assertSessionHasErrors(['model' => __('plant.model.external')]);
        expect($asset->refresh()->model_path)->toBeNull($case);
    }
    expect(AssetModel::externalUris(storage_path('nonesiste.glb')))->toBe(['?']);

    // Immagine incorporata come data: URI: accettata
    $png = 'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('p.png', 2, 2)->getContent());
    $this->post(route('assets.model.upload', $asset), ['model' => UploadedFile::fake()->createWithContent('incorporato.glb', tinyGlb(['images' => [['uri' => $png]]]))])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($asset->refresh()->model_path)->not->toBeNull();
});

it('modello GLB: mai da un indirizzo pubblico, solo dalla rotta protetta', function () {
    $asset = Asset::factory()->create();
    $this->post(route('assets.model.upload', $asset), ['model' => UploadedFile::fake()->createWithContent('m.glb', tinyGlb())])->assertRedirect();
    $path = $asset->refresh()->model_path;

    // Il file non è sotto public/ e il disco privato non lo serve senza firma
    expect(file_exists(public_path($path)))->toBeFalse()
        ->and(file_exists(public_path('storage/'.$path)))->toBeFalse()
        ->and(Storage::disk('local')->path($path))->toStartWith(config('filesystems.disks.local.root'))
        ->and(str_starts_with(config('filesystems.disks.local.root'), public_path()))->toBeFalse();
    $this->get('/storage/'.$path)->assertStatus(403);

    // Rotta protetta: accesso e permesso di vedere le macchine; tipo giusto, niente esecuzione
    $res = $this->get(route('assets.model', $asset))->assertOk()
        ->assertHeader('Content-Type', 'model/gltf-binary')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    expect($res->headers->get('Cache-Control'))->toContain('private');
    auth()->logout();
    $this->get(route('assets.model', $asset))->assertRedirect(route('login'));
    $noAssets = userWithRole(Access::REQUESTER);
    $noAssets->roles->first()->revokePermissionTo('assets.view');
    $this->actingAs($noAssets->fresh())->get(route('assets.model', $asset))->assertForbidden();
    Storage::disk('local')->delete($path);
});
