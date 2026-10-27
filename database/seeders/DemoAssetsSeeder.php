<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\Meter;
use Illuminate\Database\Seeder;

/**
 * Macchine di prova, tutte inventate: nomi generici, costruttori fittizi
 * ("Costruttore Alfa"…), nessun riferimento a prodotti o luoghi reali.
 */
class DemoAssetsSeeder extends Seeder
{
    public function run(): void
    {
        if (Asset::query()->exists()) {
            return;
        }

        $site = Location::query()->create(['name' => 'Sito principale', 'code' => 'S1']);
        $areas = [];
        foreach (['Reparto stampaggio' => 'STA', 'Reparto assemblaggio' => 'ASS', 'Servizi generali' => 'SRV'] as $name => $code) {
            $areas[$code] = Location::query()->create(['parent_id' => $site->id, 'name' => $name, 'code' => $code]);
        }
        $lines = [
            'STA-1' => Location::query()->create(['parent_id' => $areas['STA']->id, 'name' => 'Linea 1', 'code' => 'STA-L1']),
            'STA-2' => Location::query()->create(['parent_id' => $areas['STA']->id, 'name' => 'Linea 2', 'code' => 'STA-L2']),
            'ASS-1' => Location::query()->create(['parent_id' => $areas['ASS']->id, 'name' => 'Linea montaggio', 'code' => 'ASS-L1']),
        ];

        $cat = [];
        foreach ([
            'Presse' => ['press', 'press'], 'Centraline' => ['droplet', 'tank'], 'Robot' => ['bot', 'robot'],
            'Nastri trasportatori' => ['layers', 'conveyor'], 'Compressori' => ['wind', 'cylinder'], 'Impianti elettrici' => ['zap', 'box'],
            'Carrelli elevatori' => ['truck', 'box'],
        ] as $name => [$icon, $shape]) {
            $cat[$name] = AssetCategory::query()->create(['name' => $name, 'icon' => $icon === 'press' ? 'factory' : $icon, 'shape' => $shape]);
        }

        $rows = [
            // codice, nome, categoria, ubicazione, padre, costruttore, modello, anno, criticità, stato
            ['PRS-01', 'Pressa idraulica 250 t', 'Presse', $lines['STA-1'], null, 'Costruttore Alfa', 'PI-250', 2016, 'A', 'operativa'],
            ['PRS-01-CEN', 'Centralina oleodinamica', 'Centraline', $lines['STA-1'], 'PRS-01', 'Costruttore Alfa', 'CO-40', 2016, 'A', 'operativa'],
            ['PRS-02', 'Pressa meccanica 160 t', 'Presse', $lines['STA-1'], null, 'Costruttore Beta', 'PM-160', 2012, 'A', 'guasto'],
            ['PRS-03', 'Pressa idraulica 400 t', 'Presse', $lines['STA-2'], null, 'Costruttore Alfa', 'PI-400', 2020, 'A', 'operativa'],
            ['ROB-01', 'Robot di asservimento', 'Robot', $lines['STA-2'], null, 'Costruttore Gamma', 'R6-12', 2021, 'B', 'manutenzione'],
            ['NAS-01', 'Nastro di uscita pezzi', 'Nastri trasportatori', $lines['STA-2'], null, 'Costruttore Delta', 'NT-600', 2019, 'B', 'operativa'],
            ['ROB-02', 'Robot di saldatura', 'Robot', $lines['ASS-1'], null, 'Costruttore Gamma', 'R6-20', 2022, 'A', 'operativa'],
            ['NAS-02', 'Nastro di montaggio', 'Nastri trasportatori', $lines['ASS-1'], null, 'Costruttore Delta', 'NT-900', 2018, 'B', 'operativa'],
            ['AVV-01', 'Avvitatore multiplo', null, $lines['ASS-1'], null, 'Costruttore Epsilon', 'AM-8', 2017, 'C', 'ferma'],
            ['CMP-01', 'Compressore a vite 37 kW', 'Compressori', $areas['SRV'], null, 'Costruttore Zeta', 'CV-37', 2015, 'A', 'operativa'],
            ['CMP-02', 'Compressore di riserva 22 kW', 'Compressori', $areas['SRV'], null, 'Costruttore Zeta', 'CV-22', 2009, 'C', 'operativa'],
            ['QEL-01', 'Quadro elettrico generale', 'Impianti elettrici', $areas['SRV'], null, null, null, 2010, 'A', 'operativa'],
            ['CAR-01', 'Carrello elevatore elettrico', 'Carrelli elevatori', $site, null, 'Costruttore Eta', 'CE-25', 2019, 'B', 'operativa'],
            ['CAR-02', 'Carrello elevatore elettrico', 'Carrelli elevatori', $site, null, 'Costruttore Eta', 'CE-25', 2014, 'C', 'dismessa'],
        ];

        $ids = [];
        foreach ($rows as $i => [$code, $name, $category, $location, $parent, $maker, $model, $year, $crit, $status]) {
            $asset = Asset::query()->create([
                'code' => $code,
                'name' => $name,
                'asset_category_id' => $category ? $cat[$category]->id : null,
                'location_id' => $location->id,
                'parent_id' => $parent ? $ids[$parent] : null,
                'manufacturer' => $maker,
                'model' => $model,
                'year' => $year,
                'serial_number' => $maker ? sprintf('SN-%06d', 104 + $i * 37) : null,
                'installed_on' => sprintf('%d-%02d-15', $year + 1, ($i % 12) + 1),
                'warranty_until' => sprintf('%d-%02d-15', $year + 3, ($i % 12) + 1),
                'criticality' => $crit,
                'status' => $status,
            ]);
            $asset->forceFill(['status_changed_at' => now()->subHours(3 + $i * 17)])->save();
            $ids[$code] = $asset->id;
        }

        // Contatori con qualche lettura
        foreach (['PRS-01' => ['Ore di lavoro', 'h', 18240], 'PRS-01-CEN' => ['Ore pompa', 'h', 17110], 'PRS-02' => ['Colpi', 'colpi', 2_450_300], 'PRS-03' => ['Ore di lavoro', 'h', 6120], 'ROB-02' => ['Cicli', 'cicli', 812_400], 'CMP-01' => ['Ore di marcia', 'h', 31580], 'CAR-01' => ['Ore di lavoro', 'h', 4210]] as $code => [$name, $unit, $value]) {
            $meter = Meter::query()->make(['name' => $name, 'unit' => $unit]);
            $meter->asset_id = $ids[$code];
            $meter->save();
            for ($w = 8; $w >= 0; $w--) {
                $reading = $value - $w * ($unit === 'h' ? 38 : (int) ($value / 400));
                $meter->readings()->create(['value' => $reading, 'read_at' => now()->subWeeks($w)->setTime(7, 30), 'source' => 'manuale']);
            }
            $meter->forceFill(['current_value' => $value, 'last_read_at' => now()->setTime(7, 30)])->save();
        }
    }
}
