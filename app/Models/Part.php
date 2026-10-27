<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Ricambio (articolo di magazzino).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $category
 * @property string $unit
 * @property string|null $manufacturer
 * @property string|null $manufacturer_code
 * @property string|null $description
 * @property string $unit_cost costo medio ponderato
 * @property string $min_stock
 * @property string|null $reorder_qty
 * @property string $stock_total
 * @property bool $is_active
 * @property Carbon $created_at
 */
class Part extends Model
{
    /** Unità proposte */
    public const UNITS = ['pz', 'm', 'kg', 'l', 'conf', 'coppia', 'set'];

    protected $fillable = ['code', 'name', 'category', 'unit', 'manufacturer', 'manufacturer_code', 'description', 'min_stock', 'reorder_qty', 'is_active'];

    protected $attributes = ['unit' => 'pz', 'unit_cost' => 0, 'min_stock' => 0, 'stock_total' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:4',
            'min_stock' => 'decimal:2',
            'reorder_qty' => 'decimal:2',
            'stock_total' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<StockLevel, $this> */
    public function levels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /** @return BelongsToMany<Supplier, $this, PartSupplier, 'pivot'> */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)->using(PartSupplier::class)
            ->withPivot(['id', 'supplier_code', 'price', 'lead_time_days', 'is_preferred'])->withTimestamps()
            ->orderByDesc('part_supplier.is_preferred')->orderBy('name');
    }

    /** Macchine in cui il ricambio è montato (distinta) */
    /** @return BelongsToMany<Asset, $this> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class)->withPivot(['quantity', 'note'])->withTimestamps()->orderBy('code');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('position')->orderBy('id');
    }

    /** @param Builder<Part> $query */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn (Builder $q) => $q
            ->where('code', 'like', $like)
            ->orWhere('name', 'like', $like)
            ->orWhere('manufacturer_code', 'like', $like)
            ->orWhere('manufacturer', 'like', $like)
            ->orWhereHas('suppliers', fn (Builder $s) => $s->where('part_supplier.supplier_code', 'like', $like)));
    }

    /**
     * Sotto la scorta minima (o esauriti, se hanno una scorta minima).
     *
     * @param  Builder<Part>  $query
     */
    public function scopeBelowMinimum(Builder $query): void
    {
        $query->where('min_stock', '>', 0)->whereColumn('stock_total', '<=', 'min_stock');
    }

    public function isBelowMinimum(): bool
    {
        return (float) $this->min_stock > 0 && (float) $this->stock_total <= (float) $this->min_stock;
    }

    /** ok | sotto | esaurito */
    public function stockState(): string
    {
        return match (true) {
            (float) $this->stock_total <= 0 => 'esaurito',
            $this->isBelowMinimum() => 'sotto',
            default => 'ok',
        };
    }

    /** Quanto ordinare per tornare sopra la scorta minima */
    public function suggestedOrder(): float
    {
        $target = (float) $this->min_stock + (float) ($this->reorder_qty ?: $this->min_stock);

        return max(0, round($target - (float) $this->stock_total, 2));
    }

    public function preferredSupplier(): ?Supplier
    {
        return $this->suppliers->firstWhere('pivot.is_preferred', true) ?? $this->suppliers->first();
    }

    public static function formatQty(float|string|null $value): string
    {
        $value = (float) $value;

        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
