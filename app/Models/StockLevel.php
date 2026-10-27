<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Giacenza di un ricambio in un magazzino.
 *
 * @property int $id
 * @property int $part_id
 * @property int $warehouse_id
 * @property string $quantity
 * @property string|null $bin
 * @property-read Part $part
 * @property-read Warehouse $warehouse
 */
class StockLevel extends Model
{
    protected $fillable = ['part_id', 'warehouse_id', 'quantity', 'bin'];

    protected $attributes = ['quantity' => 0];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    /** @return BelongsTo<Part, $this> */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
