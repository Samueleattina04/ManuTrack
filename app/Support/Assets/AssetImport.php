<?php

namespace App\Support\Assets;

use App\Actions\Assets\SaveAsset;
use App\Enums\AssetStatus;
use App\Enums\Criticality;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Import\ReadsSpreadsheets;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Importazione delle macchine da CSV o Excel (XLSX), in due tempi:
 * 1. analyze(): legge il file, controlla ogni riga e prepara un'anteprima;
 * 2. apply(): esegue l'anteprima confermata, tutto o niente.
 *
 * Le macchine si riconoscono dal codice: quelle esistenti si aggiornano
 * (le celle vuote non cancellano i dati già presenti), le altre si creano.
 * Categorie e ubicazioni mancanti si creano; l'ubicazione si scrive come
 * "Sito > Area > Linea".
 */
class AssetImport
{
    use ReadsSpreadsheets;

    public const MAX_ROWS = 5000;

    /** colonna => nomi accettati nell'intestazione (minuscolo, senza accenti né spazi) */
    public const COLUMNS = [
        'code' => ['codice', 'code', 'cod'],
        'name' => ['nome', 'name', 'descrizione', 'macchina'],
        'category' => ['categoria', 'category', 'tipo'],
        'location' => ['ubicazione', 'location', 'posizione', 'reparto'],
        'parent' => ['macchinapadre', 'padre', 'parent', 'codicepadre'],
        'serial_number' => ['matricola', 'serial', 'numerodiserie', 'serialnumber'],
        'manufacturer' => ['costruttore', 'produttore', 'marca', 'manufacturer'],
        'model' => ['modello', 'model'],
        'year' => ['anno', 'annodicostruzione', 'year'],
        'installed_on' => ['datainstallazione', 'installazione', 'installatail', 'installedon'],
        'criticality' => ['criticita', 'criticality'],
        'status' => ['stato', 'status'],
        'purchase_cost' => ['costoacquisto', 'costodiacquisto', 'costo', 'purchasecost'],
        'warranty_until' => ['garanzia', 'garanziafinoal', 'scadenzagaranzia', 'warrantyuntil'],
        'notes' => ['note', 'notes'],
    ];

    /** Intestazioni del modello scaricabile, nell'ordine */
    public const TEMPLATE_HEADERS = ['codice', 'nome', 'categoria', 'ubicazione', 'macchina_padre', 'matricola', 'costruttore', 'modello', 'anno', 'data_installazione', 'criticita', 'stato', 'costo_acquisto', 'garanzia_fino_al', 'note'];

    /**
     * Controlla le righe e salva l'anteprima sul disco privato.
     *
     * @param  list<list<mixed>>  $rows
     * @return array{token: string, total: int, create: int, update: int, invalid: int, new_categories: list<string>, new_locations: list<string>, errors: list<array{line: int, code: string, messages: list<string>}>, unknown_columns: list<string>, sample: list<array<string, mixed>>}
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

        $existing = Asset::query()->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [mb_strtolower((string) $code) => (int) $id]);
        $categories = AssetCategory::query()->pluck('name')->map(fn ($n) => mb_strtolower($n))->flip();
        $locations = array_flip(array_map('mb_strtolower', Location::labels()));
        $fileCodes = [];
        $prepared = [];
        $errors = [];
        $newCategories = [];
        $newLocations = [];
        $counts = ['create' => 0, 'update' => 0, 'invalid' => 0];

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
            $data = $this->normalize($values, $messages);
            $key = mb_strtolower((string) $data['code']);

            if ($key !== '' && isset($fileCodes[$key])) {
                $messages[] = $this->message('assets.import.duplicate_in_file', ['line' => $fileCodes[$key]]);
            }
            $fileCodes[$key] = $line;

            $id = $existing[$key] ?? null;
            $rules = SaveAsset::rules($id ? Asset::query()->find($id) : null);
            unset($rules['asset_category_id'], $rules['location_id'], $rules['parent_id']);
            if ($id) {
                // In aggiornamento le celle vuote lasciano il valore attuale
                foreach (['criticality', 'status', 'name'] as $optional) {
                    if ($data[$optional] === null) {
                        unset($rules[$optional]);
                    }
                }
            } else {
                $data['criticality'] ??= Criticality::B->value;
                $data['status'] ??= AssetStatus::Operational->value;
            }
            $validator = Validator::make($data, $rules, [], SaveAsset::attributes());
            foreach ($validator->errors()->all() as $message) {
                $messages[] = $message;
            }

            if ($data['category'] !== null && ! isset($categories[mb_strtolower($data['category'])])) {
                $newCategories[mb_strtolower($data['category'])] = $data['category'];
            }
            if ($data['location'] !== null) {
                $parts = $this->locationParts($data['location']);
                if (count($parts) > 3) {
                    $messages[] = $this->message('assets.import.location_depth');
                } else {
                    $data['location'] = implode(' › ', $parts);
                    if (! isset($locations[mb_strtolower($data['location'])])) {
                        $newLocations[mb_strtolower($data['location'])] = $data['location'];
                    }
                }
            }

            if ($messages !== []) {
                $counts['invalid']++;
                $errors[] = ['line' => $line, 'code' => (string) $data['code'], 'messages' => array_values(array_unique($messages))];

                continue;
            }

            $counts[$id ? 'update' : 'create']++;
            $prepared[] = ['line' => $line, 'id' => $id] + $data;
        }

        // I codici padre devono esistere già o essere nel file
        $kept = [];
        foreach ($prepared as $row) {
            $parentKey = $row['parent'] !== null ? mb_strtolower($row['parent']) : null;
            if ($parentKey !== null && ($parentKey === mb_strtolower((string) $row['code']) || (! isset($existing[$parentKey]) && ! isset($fileCodes[$parentKey])))) {
                $errors[] = ['line' => $row['line'], 'code' => (string) $row['code'], 'messages' => [$this->message('assets.import.parent_missing', ['code' => $row['parent']])]];
                $counts[$row['id'] ? 'update' : 'create']--;
                $counts['invalid']++;

                continue;
            }
            $kept[] = $row;
        }

        $token = Str::lower(Str::random(24));
        Storage::disk('local')->put("imports/{$token}.json", (string) json_encode($kept));

        usort($errors, fn ($a, $b) => $a['line'] <=> $b['line']);

        return [
            'token' => $token,
            'total' => $counts['create'] + $counts['update'] + $counts['invalid'],
            'create' => $counts['create'],
            'update' => $counts['update'],
            'invalid' => $counts['invalid'],
            'new_categories' => array_values($newCategories),
            'new_locations' => array_values($newLocations),
            'errors' => array_slice($errors, 0, 200),
            'unknown_columns' => $unknown,
            'sample' => array_slice($kept, 0, 8),
        ];
    }

    /**
     * Esegue un'anteprima: tutto in una transazione, così un errore non lascia metà dati.
     *
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

        $result = DB::transaction(function () use ($rows) {
            $save = app(SaveAsset::class);
            $categoryIds = AssetCategory::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [mb_strtolower($n) => (int) $id])->all();
            $created = $updated = 0;
            $ids = [];

            foreach ($rows as $row) {
                $asset = $row['id'] ? Asset::query()->find($row['id']) : null;
                $input = $asset ? $asset->only(array_keys(SaveAsset::rules())) : [];
                if ($asset) {
                    $input['criticality'] = $asset->criticality->value;
                    $input['status'] = $asset->status->value;
                    $input['installed_on'] = $asset->installed_on?->toDateString();
                    $input['warranty_until'] = $asset->warranty_until?->toDateString();
                }
                foreach (['code', 'name', 'serial_number', 'manufacturer', 'model', 'year', 'installed_on', 'criticality', 'status', 'purchase_cost', 'warranty_until', 'notes'] as $field) {
                    if ($row[$field] !== null) {
                        $input[$field] = $row[$field];
                    }
                }
                if ($row['category'] !== null) {
                    $key = mb_strtolower($row['category']);
                    $categoryIds[$key] ??= (int) AssetCategory::query()->create(['name' => $row['category']])->id;
                    $input['asset_category_id'] = $categoryIds[$key];
                }
                if ($row['location'] !== null) {
                    $input['location_id'] = $this->ensureLocation($row['location']);
                }
                $input['parent_id'] = $asset?->parent_id;

                $saved = $save->handle($input, $asset, audit: false);
                $ids[mb_strtolower($saved->code)] = $saved->id;
                $asset ? $updated++ : $created++;
            }

            // Secondo giro: i padri, che possono essere nel file stesso
            foreach ($rows as $row) {
                if ($row['parent'] !== null) {
                    $parentId = $ids[mb_strtolower($row['parent'])] ?? Asset::query()->where('code', $row['parent'])->value('id');
                    $asset = Asset::query()->find($ids[mb_strtolower((string) $row['code'])]);
                    if ($asset && $parentId && ! in_array((int) $parentId, [$asset->id, ...$asset->descendantIds()], true)) {
                        $asset->forceFill(['parent_id' => $parentId])->save();
                    }
                }
            }

            return ['created' => $created, 'updated' => $updated];
        });

        Storage::disk('local')->delete($file);
        Audit::log('assets.imported', null, __('assets.import.audit', $result), [], $user);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $v
     * @param  list<string>  $messages
     * @return array<string, mixed>
     */
    private function normalize(array $v, array &$messages): array
    {
        $text = fn (string $k) => isset($v[$k]) ? (string) $v[$k] : null;

        $status = $text('status');
        if ($status !== null) {
            $match = collect(AssetStatus::cases())->first(fn (AssetStatus $s) => in_array(Str::ascii(mb_strtolower($status)), [$s->value, Str::ascii(mb_strtolower($s->label()))], true));
            if ($match === null) {
                $messages[] = $this->message('assets.import.status_invalid', ['value' => $status]);
            }
            $status = $match?->value;
        }

        $criticality = $text('criticality');
        if ($criticality !== null) {
            $criticality = mb_strtoupper(mb_substr($criticality, 0, 1));
            if (Criticality::tryFrom($criticality) === null) {
                $messages[] = $this->message('assets.import.criticality_invalid', ['value' => $text('criticality')]);
                $criticality = null;
            }
        }

        return [
            'code' => $text('code'),
            'name' => $text('name'),
            'category' => $text('category') !== null ? mb_strimwidth($text('category'), 0, 80, '') : null,
            'location' => $text('location'),
            'parent' => $text('parent'),
            'serial_number' => $text('serial_number'),
            'manufacturer' => $text('manufacturer'),
            'model' => $text('model'),
            'year' => isset($v['year']) ? (is_numeric($v['year']) ? (int) $v['year'] : $v['year']) : null,
            'installed_on' => $this->date($v['installed_on'] ?? null, 'installed_on', $messages),
            'criticality' => $criticality,
            'status' => $status,
            'purchase_cost' => $this->number($v['purchase_cost'] ?? null, $messages),
            'warranty_until' => $this->date($v['warranty_until'] ?? null, 'warranty_until', $messages),
            'notes' => $text('notes'),
        ];
    }

    /**
     * @param  list<string>  $messages
     */
    private function date(mixed $value, string $field, array &$messages): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            // Data di Excel salvata come numero di giorni dal 30/12/1899
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }
        $value = trim((string) $value);
        $parts = null;
        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4}|\d{2})$/', $value, $m)) {
            $year = (int) $m[3];
            $year = strlen($m[3]) === 2 ? ($year < 70 ? 2000 + $year : 1900 + $year) : $year;
            $parts = [$year, (int) $m[2], (int) $m[1]];
        } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            $parts = [(int) $m[1], (int) $m[2], (int) $m[3]];
        }
        if ($parts !== null && checkdate($parts[1], $parts[2], $parts[0])) {
            return sprintf('%04d-%02d-%02d', ...$parts);
        }
        $messages[] = $this->message('assets.import.date_invalid', ['field' => __('assets.field.'.$field), 'value' => (string) $value]);

        return null;
    }

    /**
     * @return list<string>
     */
    private function locationParts(string $path): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\s*(?:>|›|»|\/|\\\\)\s*/u', $path) ?: []), fn ($p) => $p !== ''));
    }

    private function ensureLocation(string $path): int
    {
        $parentId = null;
        foreach ($this->locationParts($path) as $name) {
            $name = mb_strimwidth($name, 0, 120, '');
            $location = Location::query()->where('parent_id', $parentId)->where('name', $name)->first()
                ?? Location::query()->create(['parent_id' => $parentId, 'name' => $name]);
            $parentId = $location->id;
        }

        return (int) $parentId;
    }
}
