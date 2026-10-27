<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tempo di lavoro su un ordine: cronometro (ended_at vuoto finché è acceso)
 * oppure inserimento a mano.
 *
 * @property int $id
 * @property int $work_order_id
 * @property int|null $user_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int $minutes
 * @property string|null $note
 * @property-read User|null $user
 * @property-read WorkOrder $workOrder
 */
class TimeEntry extends Model
{
    protected $fillable = ['user_id', 'started_at', 'ended_at', 'minutes', 'note'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'minutes' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @param Builder<TimeEntry> $query */
    public function scopeRunning(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /** Minuti, compreso il tempo che sta scorrendo */
    public function currentMinutes(): int
    {
        return $this->isRunning() ? max(0, (int) $this->started_at->diffInMinutes(now())) : $this->minutes;
    }

    /** Ferma il cronometro; un tempo sotto il mezzo minuto (avvio per sbaglio) non si conserva */
    public function stop(?Carbon $at = null): void
    {
        $at ??= now();
        $seconds = (int) $this->started_at->diffInSeconds($at);
        if ($seconds < 30) {
            $this->delete();

            return;
        }
        $this->forceFill(['ended_at' => $at, 'minutes' => (int) round($seconds / 60)])->save();
    }
}
