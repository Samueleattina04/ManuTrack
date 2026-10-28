<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Elemento della pianta: una macchina (asset_id) o una zona del pavimento
 * (location_id e/o label). Centro (x, z), dimensioni (w, d, h) in metri, rotazione in gradi.
 *
 * @property int $id
 * @property int $plant_layout_id
 * @property int|null $asset_id
 * @property int|null $location_id
 * @property string|null $label
 * @property string $x
 * @property string $z
 * @property string $w
 * @property string $d
 * @property string|null $h
 * @property int $rotation
 * @property-read PlantLayout $layout
 * @property-read Asset|null $asset
 * @property-read Location|null $location
 */
class LayoutItem extends Model
{
    protected $fillable = ['asset_id', 'location_id', 'label', 'x', 'z', 'w', 'd', 'h', 'rotation'];

    protected function casts(): array
    {
        return ['x' => 'decimal:2', 'z' => 'decimal:2', 'w' => 'decimal:2', 'd' => 'decimal:2', 'h' => 'decimal:2', 'rotation' => 'integer'];
    }

    /** @return BelongsTo<PlantLayout, $this> */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(PlantLayout::class, 'plant_layout_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isZone(): bool
    {
        return $this->asset_id === null;
    }

    public function name(): string
    {
        return $this->label ?? $this->location->name ?? $this->asset->code ?? '';
    }
}
