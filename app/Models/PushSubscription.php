<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Iscrizione alle notifiche push di un dispositivo (una persona può averne più d'una).
 *
 * @property int $id
 * @property int $user_id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $public_key
 * @property string $auth_token
 * @property string $content_encoding
 * @property string|null $device_label
 * @property string|null $platform
 * @property string|null $user_agent
 * @property Carbon|null $last_used_at
 * @property Carbon|null $last_success_at
 * @property int $failed_count
 * @property string|null $last_error
 * @property Carbon $created_at
 */
class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'content_encoding', 'device_label', 'platform', 'user_agent', 'last_used_at'];

    protected $hidden = ['public_key', 'auth_token'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public static function findByEndpoint(string $endpoint): ?self
    {
        return self::query()->where('endpoint_hash', self::hashEndpoint($endpoint))->first();
    }

    /**
     * Servizio push che gestisce l'iscrizione (Apple, Google, Mozilla, Microsoft).
     */
    public function service(): string
    {
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);

        return match (true) {
            str_contains($host, 'push.apple.com') => 'Apple',
            str_contains($host, 'googleapis.com') => 'Google',
            str_contains($host, 'mozilla') => 'Mozilla',
            str_contains($host, 'windows.com') || str_contains($host, 'microsoft') => 'Microsoft',
            default => $host,
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
