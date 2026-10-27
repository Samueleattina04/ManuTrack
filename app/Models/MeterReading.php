<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meter_id
 * @property string $value
 * @property Carbon $read_at
 * @property int|null $user_id
 * @property string $source
 * @property bool $is_reset
 * @property string|null $note
 * @property-read User|null $user
 */
class MeterReading extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['value', 'read_at', 'user_id', 'source', 'is_reset', 'note'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'read_at' => 'datetime',
            'is_reset' => 'boolean',
        ];
    }

    /** @return BelongsTo<Meter, $this> */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
