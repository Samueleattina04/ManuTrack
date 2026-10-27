<?php

namespace App\Models;

use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Avviso nel centro notifiche di una persona.
 *
 * @property int $id
 * @property int $user_id
 * @property NotificationEvent $event
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 * @property string|null $tag
 * @property bool $urgent
 * @property Carbon|null $read_at
 * @property bool $digest
 * @property Carbon|null $emailed_at
 * @property Carbon $created_at
 */
class UserNotification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'event', 'title', 'body', 'url', 'tag', 'urgent', 'digest'];

    protected function casts(): array
    {
        return [
            'event' => NotificationEvent::class,
            'urgent' => 'boolean',
            'digest' => 'boolean',
            'read_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<UserNotification> $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public static function unreadCount(User $user): int
    {
        return self::query()->where('user_id', $user->id)->whereNull('read_at')->count();
    }
}
