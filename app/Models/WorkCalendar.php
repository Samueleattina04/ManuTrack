<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Calendario di lavoro: orari settimanali (versioni con "valido dal") ed eccezioni per data.
 *
 * @property int $id
 * @property string $name
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WorkCalendar extends Model
{
    protected $fillable = ['name'];

    protected static function booted(): void
    {
        static::creating(function (WorkCalendar $c): void {
            $c->created_by ??= auth()->id();
        });
    }

    /** @return HasMany<WorkCalendarSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(WorkCalendarSchedule::class)->orderBy('valid_from');
    }

    /** @return HasMany<WorkCalendarException, $this> */
    public function exceptions(): HasMany
    {
        return $this->hasMany(WorkCalendarException::class)->orderBy('date');
    }

    /** @return HasMany<WorkCalendarAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(WorkCalendarAssignment::class);
    }
}
