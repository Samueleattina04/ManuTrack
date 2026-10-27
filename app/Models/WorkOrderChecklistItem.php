<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Voce di checklist di un ordine (copiata dal modello, poi indipendente).
 *
 * @property int $id
 * @property int $work_order_id
 * @property int $position
 * @property string $label
 * @property string $kind spunta | valore
 * @property string|null $unit
 * @property bool $required
 * @property Carbon|null $done_at
 * @property int|null $done_by
 * @property string|null $value
 * @property string|null $note
 * @property-read User|null $doneBy
 */
class WorkOrderChecklistItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['position', 'label', 'kind', 'unit', 'required'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'done_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }
}
