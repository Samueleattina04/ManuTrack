<?php

namespace App\Actions\Assets;

use App\Enums\AttachmentKind;
use App\Models\Attachment;
use App\Support\Audit\Audit;
use App\Support\Files\PhotoProcessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Salva una foto o un documento sul disco privato e lo collega a un oggetto
 * (per ora le macchine; in seguito ordini di lavoro e segnalazioni).
 */
class StoreAttachment
{
    public function __construct(private PhotoProcessor $photos) {}

    public function handle(Model $owner, UploadedFile $file, AttachmentKind $kind): Attachment
    {
        $maxBytes = self::maxMegabytes() * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException(__('assets.files.too_big', ['max' => self::maxMegabytes()]));
        }

        $disk = (string) config('manutrack.attachments.disk', 'local');
        $folder = 'attachments/'.Str::snake(class_basename($owner)).'/'.$owner->getKey();
        $name = Str::lower(Str::random(20));
        $original = $this->cleanName($file->getClientOriginalName());

        if ($kind === AttachmentKind::Photo) {
            $photo = $this->photos->process((string) $file->getRealPath());
            $path = "{$folder}/{$name}.{$photo['ext']}";
            Storage::disk($disk)->put($path, $photo['main']);
            $variants = null;
            if ($photo['thumb'] !== null) {
                $variants = ['thumb' => "{$folder}/{$name}-400.jpg"];
                Storage::disk($disk)->put($variants['thumb'], $photo['thumb']);
            }
            $data = [
                'mime' => $photo['mime'],
                'size' => strlen($photo['main']),
                'width' => $photo['width'],
                'height' => $photo['height'],
                'variants' => $variants,
                // Il nome resta leggibile ma con l'estensione vera del file salvato
                'original_name' => pathinfo($original, PATHINFO_FILENAME).'.'.$photo['ext'],
            ];
        } else {
            $ext = strtolower($file->getClientOriginalExtension());
            if (! in_array($ext, (array) config('manutrack.attachments.documents'), true)) {
                throw new RuntimeException(__('assets.files.document_invalid', ['types' => implode(', ', (array) config('manutrack.attachments.documents'))]));
            }
            $path = "{$folder}/{$name}.{$ext}";
            Storage::disk($disk)->putFileAs($folder, $file, "{$name}.{$ext}");
            $data = [
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => (int) $file->getSize(),
                'original_name' => $original,
            ];
        }

        /** @var Attachment $attachment */
        $attachment = $owner->morphMany(Attachment::class, 'attachable')->create($data + [
            'kind' => $kind,
            'disk' => $disk,
            'path' => $path,
            'position' => (int) $owner->morphMany(Attachment::class, 'attachable')->max('position') + 1,
            'user_id' => auth()->id(),
        ]);

        Audit::log('assets.file_added', $owner, $attachment->original_name);

        return $attachment;
    }

    /** Limite effettivo: il minore tra quello di ManuTrack e quelli di PHP sul server */
    public static function maxMegabytes(): int
    {
        $limits = [(int) config('manutrack.attachments.max_mb', 20)];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = self::iniBytes((string) ini_get($key));
            if ($bytes > 0) {
                $limits[] = max(1, intdiv($bytes, 1024 * 1024));
            }
        }

        return min($limits);
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function cleanName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $name) ?? 'file';
        $name = trim($name) === '' ? 'file' : trim($name);

        return mb_strimwidth($name, 0, 190, '');
    }
}
