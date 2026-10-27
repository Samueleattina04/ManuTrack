<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Un ricambio presso un fornitore: codice del fornitore, prezzo, tempi di consegna.
 *
 * @property int $id
 * @property int $part_id
 * @property int $supplier_id
 * @property string|null $supplier_code
 * @property string|null $price
 * @property int|null $lead_time_days
 * @property bool $is_preferred
 */
class PartSupplier extends Pivot
{
    public $incrementing = true;

    protected $table = 'part_supplier';

    protected function casts(): array
    {
        return ['price' => 'decimal:4', 'is_preferred' => 'boolean', 'lead_time_days' => 'integer'];
    }
}
