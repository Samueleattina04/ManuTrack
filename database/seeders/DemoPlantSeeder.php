<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Location;
use App\Models\PlantLayout;
use App\Support\Plant\LayoutEditor;
use Illuminate\Database\Seeder;

/**
 * Pianta dimostrativa del sito inventato: linee come zone sul pavimento e le macchine
 * dentro la zona della loro ubicazione. Solo dati fittizi, solo per demo e sviluppo.
 */
class DemoPlantSeeder extends Seeder
{
    public function run(): void
    {
        $site = Location::query()->where('name', 'Sito principale')->first();
        if (! DatabaseSeeder::demoAllowed() || PlantLayout::query()->exists() || ! $site) {
            return;
        }

        $editor = app(LayoutEditor::class);
        $layout = $editor->saveLayout(null, ['name' => 'Sito principale', 'location_id' => $site->id, 'width' => 48, 'depth' => 28]);

        // Zone: [ubicazione, centro x, centro z, larghezza, profondità]
        $zones = [
            ['Linea 1', 12, 6.5, 20, 9],
            ['Linea 2', 12, 18.5, 20, 9],
            ['Linea montaggio', 35, 9, 22, 12],
            ['Servizi generali', 35, 23, 22, 6],
        ];
        foreach ($zones as [$name, $x, $z, $w, $d]) {
            $location = Location::query()->where('name', $name)->first();
            if (! $location) {
                continue;
            }
            $item = $editor->addZone($layout, $location);
            $editor->update($item, ['w' => $w, 'd' => $d]);
            $editor->move($item, $x, $z);
        }

        // Ogni macchina dove c'è posto nella zona della sua ubicazione
        foreach ($layout->placeableAssets()->with(['category', 'location'])->orderBy('code')->get() as $asset) {
            /** @var Asset $asset */
            $editor->addAsset($layout, $asset);
        }
    }
}
