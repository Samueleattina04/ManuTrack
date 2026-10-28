<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Orario settimanale di un calendario, valido da una data fino alla versione successiva.
 *
 * @property int $id
 * @property int $work_calendar_id
 * @property Carbon $valid_from
 * @property Carbon $created_at
 * @property-read WorkCalendar $calendar
 */
class WorkCalendarSchedule extends Model
{
    protected $fillable = ['valid_from'];

    protected function casts(): array
    {
        return ['valid_from' => 'date'];
    }

    /** @return BelongsTo<WorkCalendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(WorkCalendar::class, 'work_calendar_id');
    }

    /** @return HasMany<WorkCalendarShift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(WorkCalendarShift::class)->orderBy('weekday')->orderBy('start');
    }

    /**
     * Si può ancora modificare: non è ancora cominciata, oppure è stata creata oggi
     * (per correggere un errore subito). Altrimenti si crea una versione nuova.
     */
    public function isEditable(): bool
    {
        return $this->valid_from->isAfter(today()) || $this->created_at->isToday();
    }
}
