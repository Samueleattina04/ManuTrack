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

        // Corsie senza ubicazione (solo un nome sul pavimento)
        foreach ([['Corsia tra le linee', 12, 12.5, 20, 2], ['Corsia principale', 35, 17.5, 22, 3]] as [$label, $x, $z, $w, $d]) {
            $item = $editor->addZone($layout, null, $label);
            $editor->update($item, ['w' => $w, 'd' => $d]);
            $editor->move($item, $x, $z);
        }

        // Macchine in fila lungo le linee, come in un reparto vero: [codice, x, z, rotazione]
        $positions = [
            'PRS-01' => [5, 5, 0], 'PRS-01-CEN' => [5, 8.5, 0], 'PRS-02' => [11, 5, 0],
            'PRS-03' => [5, 18.5, 0], 'ROB-01' => [9, 18.5, 0], 'NAS-01' => [15, 18.5, 0],
            'ROB-02' => [32, 6.5, 0], 'NAS-02' => [36, 9, 0], 'AVV-01' => [42, 11, 0],
            'CMP-01' => [28, 23, 0], 'CMP-02' => [31, 23, 0], 'QEL-01' => [42, 23, 0],
            'CAR-01' => [40, 17.5, 90],
        ];
        foreach ($layout->placeableAssets()->with(['category', 'location'])->orderBy('code')->get() as $asset) {
            /** @var Asset $asset */
            $item = $editor->addAsset($layout, $asset);
            if (isset($positions[$asset->code])) {
                [$x, $z, $rotation] = $positions[$asset->code];
                if ($rotation) {
                    $editor->update($item, ['rotation' => $rotation]);
                }
                $editor->move($item, $x, $z);
            }
        }
    }
}
