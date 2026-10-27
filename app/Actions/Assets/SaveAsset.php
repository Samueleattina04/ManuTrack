<?php

namespace App\Actions\Assets;

use App\Enums\AssetStatus;
use App\Enums\Criticality;
use App\Models\Asset;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

/**
 * Crea o modifica una macchina (dal modulo e dall'importazione).
 */
class SaveAsset
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?Asset $asset = null): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[\pL\pN][\pL\pN._\/-]*$/u', Rule::unique('assets', 'code')->ignore($asset?->id)],
            'name' => ['required', 'string', 'max:150'],
            'asset_category_id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'parent_id' => ['nullable', 'integer', 'exists:assets,id'],
            'serial_number' => ['nullable', 'string', 'max:80'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'installed_on' => ['nullable', 'date', 'before_or_equal:'.now()->addYear()->toDateString()],
            'criticality' => ['required', new Enum(Criticality::class)],
            'status' => ['required', new Enum(AssetStatus::class)],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'warranty_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?Asset $asset = null, bool $audit = true): Asset
    {
        $data = Validator::make($input, self::rules($asset), [], self::attributes())->validate();

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value) === '' ? null : trim($value);
            }
        }
        $data['code'] = (string) $data['code'];

        // Una macchina non può stare dentro se stessa o dentro un suo sottogruppo
        if ($asset && $data['parent_id'] && ((int) $data['parent_id'] === $asset->id || in_array((int) $data['parent_id'], $asset->descendantIds(), true))) {
            throw ValidationException::withMessages(['parent_id' => __('assets.errors.parent_cycle')]);
        }

        $creating = $asset === null;
        $asset ??= new Asset;
        $previousStatus = $asset->exists ? $asset->status : null;
        $asset->fill($data);
        if ($creating || $previousStatus !== $asset->status) {
            $asset->status_changed_at = now();
        }
        $asset->save();

        if ($audit) {
            Audit::log($creating ? 'assets.created' : 'assets.updated', $asset, $asset->code.' · '.$asset->name, $creating ? [] : Audit::diff($asset));
        }

        return $asset;
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return collect(array_keys(self::rules()))->mapWithKeys(fn ($k) => [$k => __('assets.field.'.$k)])->all();
    }
}
