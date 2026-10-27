<?php

namespace App\Actions\Inventory;

use App\Models\Part;
use App\Support\Audit\Audit;
use App\Support\Numbers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Crea o modifica l'anagrafica di un ricambio (dal modulo e dall'importazione).
 * Le giacenze non si toccano qui: passano solo dai movimenti.
 */
class SavePart
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?Part $part = null): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[\pL\pN][\pL\pN._\/-]*$/u', Rule::unique('parts', 'code')->ignore($part?->id)],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:10'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'manufacturer_code' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'reorder_qty' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return collect(array_keys(self::rules()))->mapWithKeys(fn ($k) => [$k => __('inventory.field.'.$k)])->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?Part $part = null, bool $audit = true): Part
    {
        foreach (['min_stock', 'reorder_qty'] as $k) {
            if (isset($input[$k]) && is_string($input[$k]) && $input[$k] !== '') {
                $input[$k] = Numbers::parse($input[$k]) ?? $input[$k];
            }
        }
        $data = Validator::make($input, self::rules($part), [], self::attributes())->validate();
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = trim($v) === '' ? null : trim($v);
            }
        }
        $data['min_stock'] = $data['min_stock'] ?? 0;
        $data['unit'] = $data['unit'] ?? 'pz';

        $creating = $part === null;
        $part ??= new Part;
        $part->fill($data)->save();

        if ($audit) {
            Audit::log($creating ? 'inventory.part_created' : 'inventory.part_updated', $part, $part->code.' · '.$part->name, $creating ? [] : Audit::diff($part));
        }

        return $part;
    }
}
