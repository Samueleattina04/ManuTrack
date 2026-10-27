<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $work_order_id
 * @property int|null $user_id
 * @property string $body
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class WorkOrderComment extends Model
{
    protected $fillable = ['user_id', 'body'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
