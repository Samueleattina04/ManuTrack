<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Esportazione completa dei dati dell'azienda (ZIP sul disco privato, scade dopo qualche giorno).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $status in_coda|in_corso|pronto|errore
 * @property string|null $path
 * @property int|null $size
 * @property int|null $tables_count
 * @property int|null $files_count
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class DataExport extends Model
{
    /** Giorni dopo cui lo ZIP viene cancellato dal server */
    public const KEEP_DAYS = 7;

    protected $fillable = ['user_id', 'status'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'expires_at' => 'datetime', 'size' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['in_coda', 'in_corso'], true);
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'pronto' && $this->path !== null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
