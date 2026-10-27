<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property int|null $location_id
 * @property string|null $description
 * @property bool $is_active
 * @property-read Location|null $location
 */
class Warehouse extends Model
{
    protected $fillable = ['name', 'code', 'location_id', 'description', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<StockLevel, $this> */
    public function levels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /** @param Builder<Warehouse> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
