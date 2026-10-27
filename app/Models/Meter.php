<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Contatore di una macchina (ore di lavoro, cicli, pezzi, km…).
 *
 * @property int $id
 * @property int $asset_id
 * @property string $name
 * @property string $unit
 * @property bool $cumulative
 * @property string|null $current_value
 * @property Carbon|null $last_read_at
 * @property-read Asset $asset
 */
class Meter extends Model
{
    /** Unità proposte (si può scriverne un'altra) */
    public const UNITS = ['h', 'cicli', 'pezzi', 'km', 'colpi', 'kWh', 'm³'];

    protected $fillable = ['name', 'unit', 'cumulative'];

    protected $attributes = ['cumulative' => true];

    protected function casts(): array
    {
        return [
            'cumulative' => 'boolean',
            'current_value' => 'decimal:2',
            'last_read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return HasMany<MeterReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class)->orderByDesc('read_at')->orderByDesc('id');
    }
}
