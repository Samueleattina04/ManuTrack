<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Turno di un giorno della settimana (1 = lunedì … 7 = domenica), in ora locale.
 * Se la fine non viene dopo l'inizio, il turno finisce il giorno dopo (es. 22:00–06:00; 00:00–00:00 = 24 ore).
 *
 * @property int $id
 * @property int $work_calendar_schedule_id
 * @property int $weekday
 * @property string $start
 * @property string $end
 */
class WorkCalendarShift extends Model
{
    public $timestamps = false;

    protected $fillable = ['weekday', 'start', 'end'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
