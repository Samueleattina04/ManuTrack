<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $work_order_id
 * @property WorkOrderStatus|null $from
 * @property WorkOrderStatus $to
 * @property int|null $user_id
 * @property string|null $note
 * @property bool $is_correction
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class WorkOrderStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['from', 'to', 'user_id', 'note', 'is_correction'];

    protected function casts(): array
    {
        return ['from' => WorkOrderStatus::class, 'to' => WorkOrderStatus::class, 'is_correction' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
