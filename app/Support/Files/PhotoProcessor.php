<?php

namespace App\Support\Files;

use App\Support\Setup\Requirements;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

/**
 * Prepara una foto per l'archivio:
 * - la raddrizza secondo l'orientamento scattato dal telefono;
 * - la ridimensiona (lato lungo 1600 px) e crea la miniatura (400 px);
 * - la ricodifica in JPEG, che elimina tutti i metadati (posizione GPS compresa).
 *
 * Senza GD né Imagick la foto resta com'è (ma senza metadati se è un JPEG)
 * e la miniatura manca: la pagina Sistema lo segnala.
 */
class PhotoProcessor
{
    public const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * @return array{main: string, mime: string, ext: string, width: int|null, height: int|null, thumb: string|null}
     */
    public function process(string $sourcePath): array
    {
        $info = @getimagesize($sourcePath);
        $mime = $info['mime'] ?? '';
        if (! isset(self::MIMES[$mime])) {
            throw new RuntimeException(__('assets.files.photo_invalid'));
        }

        $driver = Requirements::imageDriver();
        if ($driver === null) {
            $binary = (string) file_get_contents($sourcePath);

            return [
                'main' => $mime === 'image/jpeg' ? JpegMetadata::strip($binary) : $binary,
                'mime' => $mime,
                'ext' => self::MIMES[$mime],
                'width' => (int) $info[0] ?: null,
                'height' => (int) $info[1] ?: null,
                'thumb' => null,
            ];
        }

        $this->raiseMemoryLimit((int) $info[0], (int) $info[1]);

        try {
            $manager = $driver === 'imagick' ? ImageManager::imagick(autoOrientation: true, strip: true) : ImageManager::gd(autoOrientation: true, strip: true);
            $max = (int) config('manutrack.attachments.photo_max', 1600);
            $thumbMax = (int) config('manutrack.attachments.thumb_max', 400);

            $image = $manager->read($sourcePath)->scaleDown($max, $max);
            // Le trasparenze dei PNG diventano bianco: il JPEG non le supporta
            $main = (string) $image->toJpeg(quality: 82, progressive: true, strip: true);
            [$width, $height] = [$image->width(), $image->height()];
            $thumb = (string) $image->scaleDown($thumbMax, $thumbMax)->toJpeg(quality: 78, strip: true);
        } catch (Throwable $e) {
            throw new RuntimeException(__('assets.files.photo_unreadable'), previous: $e);
        }

        return ['main' => $main, 'mime' => 'image/jpeg', 'ext' => 'jpg', 'width' => $width, 'height' => $height, 'thumb' => $thumb];
    }

    /**
     * Una foto da 12 megapixel occupa circa 50 MB in memoria mentre si lavora:
     * sugli hosting con limite basso lo si alza solo per questa operazione.
     */
    private function raiseMemoryLimit(int $width, int $height): void
    {
        $needed = (int) ($width * $height * 5 * 1.8) + 64 * 1024 * 1024;
        $current = $this->bytes((string) ini_get('memory_limit'));
        if ($current !== -1 && $current < $needed) {
            @ini_set('memory_limit', (string) min(max($needed, 256 * 1024 * 1024), 512 * 1024 * 1024));
        }
    }

    private function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
