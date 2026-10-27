<?php

namespace App\Models;

use App\Enums\AttachmentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * File allegato (foto o documento) salvato sul disco privato.
 * Si scarica solo tramite AttachmentController, che controlla i permessi.
 *
 * @property int $id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property AttachmentKind $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property array<string, string>|null $variants
 * @property int $position
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class Attachment extends Model
{
    /** Avviso per chi ha caricato il file (non salvato), es. foto HEIC conservata come documento */
    public ?string $notice = null;

    protected $fillable = ['kind', 'disk', 'path', 'original_name', 'mime', 'size', 'width', 'height', 'variants', 'position', 'user_id'];

    protected function casts(): array
    {
        return [
            'kind' => AttachmentKind::class,
            'variants' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // I file seguono la riga: niente file orfani sul disco
        static::deleted(function (Attachment $attachment): void {
            $disk = Storage::disk($attachment->disk);
            $disk->delete(array_values(array_filter([$attachment->path, ...array_values($attachment->variants ?? [])])));
        });
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPhoto(): bool
    {
        return $this->kind === AttachmentKind::Photo;
    }

    /** Percorso del file per la variante richiesta ("thumb" o l'originale) */
    public function pathFor(?string $variant): string
    {
        return ($variant !== null ? ($this->variants[$variant] ?? null) : null) ?? $this->path;
    }

    public function url(?string $variant = null): string
    {
        return route('attachments.show', array_filter(['attachment' => $this->id, 'v' => $variant, 't' => $this->updated_at?->timestamp]));
    }

    public function downloadUrl(): string
    {
        return route('attachments.download', $this->id);
    }

    public function humanSize(): string
    {
        $size = $this->size;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? $size : number_format($size, 1, ',', '.')).' '.$unit;
            }
            $size /= 1024;
        }

        return $size.' B';
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }
}
