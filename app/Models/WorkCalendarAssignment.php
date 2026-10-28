<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Da quando un livello (azienda, ubicazione, macchina) usa un calendario.
 * Calendario vuoto = eredita dal livello sopra; in cima, 24 ore su 24.
 * L'assegnazione alla singola macchina è prevista dal calcolo ma non compare nelle pagine.
 *
 * @property int $id
 * @property string $scope azienda|ubicazione|macchina
 * @property int|null $location_id
 * @property int|null $asset_id
 * @property int|null $work_calendar_id
 * @property Carbon $valid_from
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read WorkCalendar|null $calendar
 * @property-read Location|null $location
 */
class WorkCalendarAssignment extends Model
{
    public const SCOPES = ['azienda', 'ubicazione', 'macchina'];

    protected $fillable = ['scope', 'location_id', 'asset_id', 'work_calendar_id', 'valid_from'];

    protected function casts(): array
    {
        return ['valid_from' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkCalendarAssignment $a): void {
            $a->created_by ??= auth()->id();
        });
    }

    /** @return BelongsTo<WorkCalendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(WorkCalendar::class, 'work_calendar_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
