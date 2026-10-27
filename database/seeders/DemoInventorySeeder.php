<?php

namespace Database\Seeders;

use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Support\Access\Access;
use App\Support\Inventory\Stock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Magazzini, fornitori e ricambi di prova, tutti inventati, con qualche
 * movimento: carichi, un prelievo su un ordine aperto e un paio di ricambi
 * sotto scorta per mostrare il riordino.
 */
class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        if (! DatabaseSeeder::demoAllowed() || Part::query()->exists()) {
            return;
        }

        $keeper = User::query()->firstOrCreate(['email' => 'magazzino@example.com'], ['name' => 'Magazziniere Demo', 'password' => Hash::make(DatabaseSeeder::DEMO_PASSWORD)]);
        $keeper->forceFill(['email_verified_at' => now(), 'job_title' => 'Magazziniere'])->save();
        $keeper->syncRoles([Access::STOREKEEPER]);

        $main = Warehouse::query()->create(['name' => 'Magazzino centrale', 'code' => 'MC']);
        $line = Warehouse::query()->create(['name' => 'Armadio linea stampaggio', 'code' => 'AL1']);

        $suppliers = [];
        foreach ([
            ['Fornitore Alfa Ricambi', 'Ufficio vendite', 'ordini@fornitore-alfa.example', '+39 000 000 0001'],
            ['Fornitore Beta Oleodinamica', 'Referente tecnico', 'info@fornitore-beta.example', '+39 000 000 0002'],
            ['Fornitore Gamma Elettrica', null, 'vendite@fornitore-gamma.example', null],
        ] as [$name, $contact, $email, $phone]) {
            $suppliers[] = Supplier::query()->create(['name' => $name, 'contact_name' => $contact, 'email' => $email, 'phone' => $phone]);
        }
        [$alfa, $beta, $gamma] = $suppliers;

        $stock = app(Stock::class);
        // codice, nome, categoria, unità, costruttore, cod. costruttore, minimo, riordino, fornitore, cod. fornitore, prezzo, giacenza centrale, giacenza linea, scaffale, macchine
        $rows = [
            ['CUS-6205', 'Cuscinetto a sfere 6205-2RS', 'Cuscinetti', 'pz', 'Costruttore Cuscinetti', '6205-2RS', 4, 10, $alfa, 'A-6205', 7.5, 12, 2, 'A-01-1', ['NAS-01', 'NAS-02']],
            ['CUS-6308', 'Cuscinetto a sfere 6308', 'Cuscinetti', 'pz', 'Costruttore Cuscinetti', '6308-ZZ', 2, 4, $alfa, 'A-6308', 18.9, 1, 0, 'A-01-2', ['CMP-01']],
            ['CIN-SPA1250', 'Cinghia trapezoidale SPA 1250', 'Cinghie', 'pz', null, null, 2, 4, $alfa, 'A-SPA1250', 14.2, 3, 0, 'A-02-1', ['CMP-01', 'CMP-02']],
            ['GUA-CIL80', 'Kit guarnizioni cilindro Ø 80', 'Guarnizioni', 'kit', 'Costruttore Alfa', 'KG-80', 1, 2, $beta, 'B-KG80', 96.0, 0, 0, 'B-01-1', ['PRS-01', 'PRS-02', 'PRS-03']],
            ['FIL-OLIO10', 'Filtro olio idraulico 10 µm', 'Filtri', 'pz', 'Costruttore Alfa', 'FO-10', 3, 6, $beta, 'B-FO10', 32.4, 5, 1, 'B-02-1', ['PRS-01-CEN']],
            ['OLIO-ISO46', 'Olio idraulico ISO VG 46', 'Lubrificanti', 'l', null, null, 40, 100, $beta, 'B-ISO46', 3.1, 120, 0, 'C-00', ['PRS-01-CEN']],
            ['FIL-ARIA37', 'Filtro aria compressore 37 kW', 'Filtri', 'pz', 'Costruttore Zeta', 'FA-37', 1, 2, $alfa, 'A-FA37', 41.0, 2, 0, 'A-03-1', ['CMP-01']],
            ['FUS-10A', 'Fusibile 10 A ritardato', 'Materiale elettrico', 'pz', null, null, 10, 20, $gamma, 'G-F10A', 0.85, 34, 6, 'D-01-1', ['QEL-01']],
            ['LAM-24V', 'Lampada di segnalazione 24 V', 'Materiale elettrico', 'pz', null, null, 2, 5, $gamma, 'G-L24', 6.4, 3, 0, 'D-01-2', ['ROB-01', 'ROB-02']],
            ['SEN-IND12', 'Sensore induttivo M12', 'Sensori', 'pz', 'Costruttore Gamma', 'SI-M12', 2, 3, $gamma, 'G-SIM12', 38.0, 4, 0, 'D-02-1', ['ROB-01', 'NAS-01']],
            ['GRA-EP2', 'Grasso al litio EP2', 'Lubrificanti', 'kg', null, null, 2, 5, null, null, 9.8, 6, 0, 'C-01', []],
        ];

        $assets = Asset::query()->pluck('id', 'code');
        $parts = [];
        foreach ($rows as [$code, $name, $category, $unit, $maker, $makerCode, $min, $reorder, $supplier, $supplierCode, $price, $qtyMain, $qtyLine, $bin, $usedIn]) {
            $part = Part::query()->create(['code' => $code, 'name' => $name, 'category' => $category, 'unit' => $unit, 'manufacturer' => $maker, 'manufacturer_code' => $makerCode, 'min_stock' => $min, 'reorder_qty' => $reorder]);
            if ($supplier) {
                $part->suppliers()->attach($supplier->id, ['supplier_code' => $supplierCode, 'price' => $price, 'lead_time_days' => 5, 'is_preferred' => true]);
            }
            $part->levels()->create(['warehouse_id' => $main->id, 'bin' => $bin]);
            if ($qtyMain > 0) {
                $stock->receive($part, $main, $qtyMain, $keeper, $price, $supplier, 'DDT 0001', __('inventory.part.initial_note'));
            }
            if ($qtyLine > 0) {
                $stock->receive($part, $line, $qtyLine, $keeper, $price, $supplier, 'DDT 0001', __('inventory.part.initial_note'));
            }
            $part->assets()->attach(collect($usedIn)->map(fn ($c) => $assets[$c] ?? null)->filter()->mapWithKeys(fn ($id) => [$id => ['quantity' => 1]])->all());
            $parts[$code] = $part;
        }

        // Un nuovo carico a prezzo diverso (il costo medio cambia) e i prelievi per l'ordine aperto
        $stock->receive($parts['FIL-OLIO10'], $main, 4, $keeper, 35.0, $beta, 'DDT 0002');
        $order = WorkOrder::query()->whereIn('status', [WorkOrderStatus::InProgress->value, WorkOrderStatus::Waiting->value])
            ->whereHas('asset', fn ($q) => $q->where('code', 'PRS-02'))->first();
        $tech = User::query()->where('email', 'tecnico1@example.com')->first();
        if ($order && $tech) {
            $stock->issue($parts['OLIO-ISO46'], $main, 25, $tech, $order);
            $stock->issue($parts['FIL-OLIO10'], $main, 1, $tech, $order);
        }
    }
}
