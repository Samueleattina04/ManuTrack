<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fermo di una macchina (per MTTR e MTBF). Aperto finché ended_at è vuoto.
 *
 * @property int $id
 * @property int $asset_id
 * @property int|null $work_order_id
 * @property int|null $work_request_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property string|null $note
 * @property-read Asset $asset
 * @property-read WorkOrder|null $workOrder
 */
class DowntimeEvent extends Model
{
    protected $fillable = ['asset_id', 'work_order_id', 'work_request_id', 'started_at', 'ended_at', 'note'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @param Builder<DowntimeEvent> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function minutes(): int
    {
        return (int) $this->started_at->diffInMinutes($this->ended_at ?? now());
    }
}
