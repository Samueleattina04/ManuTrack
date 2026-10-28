<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Giorno diverso dal solito: chiuso (festività, chiusura aziendale), orario speciale,
 * oppure lavorativo (una festività proposta che l'azienda ha tolto: si lavora come al solito).
 *
 * @property int $id
 * @property int $work_calendar_id
 * @property Carbon $date
 * @property string $kind chiuso|orario|lavorativo
 * @property string|null $label
 * @property string $source festivita|manuale
 * @property list<array{0: string, 1: string}>|null $hours
 * @property-read WorkCalendar $calendar
 */
class WorkCalendarException extends Model
{
    public const KINDS = ['chiuso', 'orario', 'lavorativo'];

    protected $fillable = ['date', 'kind', 'label', 'source', 'hours'];

    protected function casts(): array
    {
        return ['date' => 'date', 'hours' => 'array'];
    }

    /** @return BelongsTo<WorkCalendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(WorkCalendar::class, 'work_calendar_id');
    }

    /** I giorni passati non si toccano più: gli indicatori dei mesi chiusi non cambiano */
    public function isEditable(): bool
    {
        return ! $this->date->isBefore(today());
    }
}
