<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Registro di installazione e aggiornamenti.
 *
 * @property int $id
 * @property string $version
 * @property string|null $previous_version
 * @property string $status running|success|failed
 * @property string $trigger install|manual|auto|web
 * @property string|null $backup_file
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class SystemVersion extends Model
{
    public const RUNNING = 'running';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    protected $fillable = [
        'version', 'previous_version', 'status', 'trigger', 'backup_file', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function durationSeconds(): ?int
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->finished_at);
    }
}
