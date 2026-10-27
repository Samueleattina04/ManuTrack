<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Movimento di magazzino. I movimenti non si modificano né si cancellano:
 * un errore si corregge con un movimento opposto (reso o rettifica).
 *
 * @property int $id
 * @property int $part_id
 * @property int $warehouse_id
 * @property MovementType $type
 * @property string $quantity con segno: positivo entra, negativo esce
 * @property string $unit_cost
 * @property string $balance_after
 * @property int|null $work_order_id
 * @property int|null $supplier_id
 * @property int|null $user_id
 * @property string|null $transfer_group
 * @property string|null $reference
 * @property string|null $note
 * @property Carbon $created_at
 * @property-read Part $part
 * @property-read Warehouse $warehouse
 * @property-read WorkOrder|null $workOrder
 * @property-read Supplier|null $supplier
 * @property-read User|null $user
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['part_id', 'warehouse_id', 'type', 'quantity', 'unit_cost', 'balance_after', 'work_order_id', 'supplier_id', 'user_id', 'transfer_group', 'reference', 'note'];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'balance_after' => 'decimal:2',
        ];
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

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Valore del movimento (positivo per le uscite verso un ordine) */
    public function value(): float
    {
        return round(abs((float) $this->quantity) * (float) $this->unit_cost, 2);
    }
}
