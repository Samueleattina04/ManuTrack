<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pianta di uno stabilimento o reparto: pavimento in metri con macchine e zone.
 *
 * @property int $id
 * @property string $name
 * @property int|null $location_id
 * @property string $width metri
 * @property string $depth metri
 * @property int $sort
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Location|null $location
 */
class PlantLayout extends Model
{
    public const MIN_SIZE = 2;

    public const MAX_SIZE = 500;

    protected $fillable = ['name', 'location_id', 'width', 'depth', 'sort'];

    protected function casts(): array
    {
        return ['width' => 'decimal:2', 'depth' => 'decimal:2', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (PlantLayout $layout): void {
            $layout->created_by ??= auth()->id();
        });
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<LayoutItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(LayoutItem::class);
    }

    /**
     * Macchine che si possono mettere in questa pianta: quelle dell'ubicazione e delle sue
     * sotto-ubicazioni (tutte, se la pianta non ha un'ubicazione), non dismesse.
     *
     * @return Builder<Asset>
     */
    public function placeableAssets(): Builder
    {
        return Asset::query()
            ->when($this->location, fn ($q) => $q->inLocation($this->location))
            ->where('status', '!=', 'dismessa');
    }
}
