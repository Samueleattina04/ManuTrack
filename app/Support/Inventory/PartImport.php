<?php

namespace App\Support\Inventory;

use App\Actions\Inventory\SavePart;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Audit\Audit;
use App\Support\Import\ReadsSpreadsheets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Importazione dei ricambi da CSV o Excel, con anteprima e conferma come per le macchine.
 *
 * I ricambi si riconoscono dal codice: quelli esistenti si aggiornano (le celle
 * vuote non cancellano nulla), gli altri si creano. La giacenza del file vale
 * solo per i ricambi nuovi e diventa un carico iniziale; per quelli esistenti
 * le quantità si cambiano con una rettifica, così il registro resta coerente.
 * I fornitori mancanti si creano; i magazzini devono esistere già.
 */
class PartImport
{
    use ReadsSpreadsheets;

    public const MAX_ROWS = 5000;

    public const COLUMNS = [
        'code' => ['codice', 'code', 'cod', 'codicericambio', 'articolo'],
        'name' => ['nome', 'name', 'descrizionebreve', 'ricambio'],
        'category' => ['categoria', 'category', 'famiglia'],
        'unit' => ['unita', 'um', 'unitadimisura', 'unit'],
        'manufacturer' => ['costruttore', 'produttore', 'marca', 'manufacturer'],
        'manufacturer_code' => ['codicecostruttore', 'codiceproduttore', 'partnumber', 'pn'],
        'description' => ['descrizione', 'description', 'note'],
        'min_stock' => ['scortaminima', 'minimo', 'min', 'minstock'],
        'reorder_qty' => ['quantitariordino', 'riordino', 'lotto', 'reorderqty'],
        'supplier' => ['fornitore', 'supplier'],
        'supplier_code' => ['codicefornitore', 'suppliercode'],
        'supplier_price' => ['prezzofornitore', 'prezzo', 'price'],
        'warehouse' => ['magazzino', 'warehouse'],
        'stock' => ['giacenza', 'quantita', 'stock', 'qta'],
        'unit_cost' => ['costounitario', 'costo', 'unitcost'],
        'bin' => ['scaffale', 'ubicazione', 'bin'],
    ];

    public const TEMPLATE_HEADERS = ['codice', 'nome', 'categoria', 'unita', 'costruttore', 'codice_costruttore', 'descrizione', 'scorta_minima', 'quantita_riordino', 'fornitore', 'codice_fornitore', 'prezzo_fornitore', 'magazzino', 'giacenza', 'costo_unitario', 'scaffale'];

    /**
     * @param  list<list<mixed>>  $rows
     * @return array{token: string, total: int, create: int, update: int, invalid: int, stock_ignored: int, new_suppliers: list<string>, errors: list<array{line: int, code: string, messages: list<string>}>, unknown_columns: list<string>, sample: list<array<string, mixed>>}
     */
    public function analyze(array $rows): array
    {
        if (count($rows) < 2) {
            throw new RuntimeException(__('assets.import.empty'));
        }
        if (count($rows) > self::MAX_ROWS + 1) {
            throw new RuntimeException(__('assets.import.too_many', ['max' => self::MAX_ROWS]));
        }

        [$map, $unknown] = $this->mapHeader(array_shift($rows));
        if (! isset($map['code']) || ! isset($map['name'])) {
            throw new RuntimeException(__('assets.import.missing_columns'));
        }

        $existing = Part::query()->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [mb_strtolower((string) $code) => (int) $id]);
        $suppliers = Supplier::query()->pluck('name')->map(fn ($n) => mb_strtolower($n))->flip();
        $warehouses = Warehouse::query()->active()->pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [mb_strtolower((string) $n) => (int) $id]);
        $defaultWarehouse = Warehouse::query()->active()->orderBy('id')->value('id');
        $fileCodes = [];
        $kept = [];
        $errors = [];
        $newSuppliers = [];
        $counts = ['create' => 0, 'update' => 0, 'invalid' => 0, 'stock_ignored' => 0];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $values = [];
            foreach ($map as $field => $index) {
                $values[$field] = $this->cell($row[$index] ?? null);
            }
            if (collect($values)->filter(fn ($v) => $v !== null)->isEmpty()) {
                continue;
            }

            $messages = [];
            $text = fn (string $k) => isset($values[$k]) ? (string) $values[$k] : null;
            $data = [
                'code' => $text('code'), 'name' => $text('name'), 'category' => $text('category'), 'unit' => $text('unit'),
                'manufacturer' => $text('manufacturer'), 'manufacturer_code' => $text('manufacturer_code'), 'description' => $text('description'),
                'min_stock' => $this->number($values['min_stock'] ?? null, $messages),
                'reorder_qty' => $this->number($values['reorder_qty'] ?? null, $messages),
                'supplier' => $text('supplier') !== null ? mb_strimwidth($text('supplier'), 0, 150, '') : null,
                'supplier_code' => $text('supplier_code'),
                'supplier_price' => $this->number($values['supplier_price'] ?? null, $messages),
                'warehouse_id' => null,
                'stock' => $this->number($values['stock'] ?? null, $messages),
                'unit_cost' => $this->number($values['unit_cost'] ?? null, $messages),
                'bin' => $text('bin') !== null ? mb_strimwidth($text('bin'), 0, 40, '') : null,
            ];
            $key = mb_strtolower((string) $data['code']);
            if ($key !== '' && isset($fileCodes[$key])) {
                $messages[] = $this->message('assets.import.duplicate_in_file', ['line' => $fileCodes[$key]]);
            }
            $fileCodes[$key] = $line;

            $id = $existing[$key] ?? null;
            $rules = SavePart::rules($id ? Part::query()->find($id) : null);
            if ($id) {
                foreach (['name', 'unit'] as $optional) {
                    if ($data[$optional] === null) {
                        unset($rules[$optional]);
                    }
                }
            } else {
                $data['unit'] ??= 'pz';
            }
            foreach (Validator::make($data, $rules, [], SavePart::attributes())->errors()->all() as $message) {
                $messages[] = $message;
            }

            if ($data['stock'] !== null) {
                if ($id) {
                    $counts['stock_ignored']++;
                    $data['stock'] = null;
                } elseif ($data['stock'] < 0) {
                    $messages[] = $this->message('inventory.import.stock_negative');
                } elseif ($data['stock'] > 0) {
                    $name = $text('warehouse');
                    $data['warehouse_id'] = $name !== null ? ($warehouses[mb_strtolower($name)] ?? null) : $defaultWarehouse;
                    if ($data['warehouse_id'] === null) {
                        $messages[] = $name !== null
                            ? $this->message('inventory.import.warehouse_missing', ['name' => $name])
                            : $this->message('inventory.import.no_warehouse');
                    }
                }
            }
            if ($data['supplier'] !== null && ! isset($suppliers[mb_strtolower($data['supplier'])])) {
                $newSuppliers[mb_strtolower($data['supplier'])] = $data['supplier'];
            }

            if ($messages !== []) {
                $counts['invalid']++;
                $errors[] = ['line' => $line, 'code' => (string) $data['code'], 'messages' => array_values(array_unique($messages))];

                continue;
            }

            $counts[$id ? 'update' : 'create']++;
            $kept[] = ['line' => $line, 'id' => $id] + $data;
        }

        $token = Str::lower(Str::random(24));
        Storage::disk('local')->put("imports/{$token}.json", (string) json_encode($kept));

        return [
            'token' => $token,
            'total' => $counts['create'] + $counts['update'] + $counts['invalid'],
            'create' => $counts['create'],
            'update' => $counts['update'],
            'invalid' => $counts['invalid'],
            'stock_ignored' => $counts['stock_ignored'],
            'new_suppliers' => array_values($newSuppliers),
            'errors' => array_slice($errors, 0, 200),
            'unknown_columns' => $unknown,
            'sample' => array_slice($kept, 0, 8),
        ];
    }

    /**
     * @return array{created: int, updated: int}
     */
    public function apply(string $token, User $user): array
    {
        abort_unless(preg_match('/^[a-z0-9]{24}$/', $token) === 1, 404);
        $file = "imports/{$token}.json";
        $rows = json_decode((string) Storage::disk('local')->get($file), true);
        if (! is_array($rows)) {
            throw new RuntimeException(__('assets.import.expired'));
        }

        $result = DB::transaction(function () use ($rows, $user) {
            $save = app(SavePart::class);
            $stock = app(Stock::class);
            $supplierIds = Supplier::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [mb_strtolower($n) => (int) $id])->all();
            $created = $updated = 0;

            foreach ($rows as $row) {
                $part = $row['id'] ? Part::query()->find($row['id']) : null;
                $input = $part ? $part->only(array_keys(SavePart::rules())) : ['is_active' => true];
                foreach (['code', 'name', 'category', 'unit', 'manufacturer', 'manufacturer_code', 'description', 'min_stock', 'reorder_qty'] as $field) {
                    if ($row[$field] !== null) {
                        $input[$field] = $row[$field];
                    }
                }
                $saved = $save->handle($input, $part, audit: false);

                $supplier = null;
                if ($row['supplier'] !== null) {
                    $key = mb_strtolower($row['supplier']);
                    $supplierIds[$key] ??= (int) Supplier::query()->create(['name' => $row['supplier']])->id;
                    $supplier = Supplier::query()->find($supplierIds[$key]);
                    $pivot = array_filter(['supplier_code' => $row['supplier_code'], 'price' => $row['supplier_price']], fn ($v) => $v !== null);
                    if ($saved->suppliers()->whereKey($supplierIds[$key])->exists()) {
                        $saved->suppliers()->updateExistingPivot($supplierIds[$key], $pivot);
                    } else {
                        $saved->suppliers()->attach($supplierIds[$key], $pivot + ['is_preferred' => ! $saved->suppliers()->exists()]);
                    }
                }

                if (! $part && $row['warehouse_id'] !== null && (float) $row['stock'] > 0) {
                    $warehouse = Warehouse::query()->findOrFail($row['warehouse_id']);
                    $saved->levels()->updateOrCreate(['warehouse_id' => $warehouse->id], ['bin' => $row['bin']]);
                    $stock->receive($saved, $warehouse, $row['stock'], $user, $row['unit_cost'] ?? $row['supplier_price'], $supplier, note: __('inventory.import.initial_note'));
                }
                $part ? $updated++ : $created++;
            }

            return ['created' => $created, 'updated' => $updated];
        });

        Storage::disk('local')->delete($file);
        Audit::log('inventory.imported', null, __('inventory.import.audit', $result), [], $user);

        return $result;
    }
}
