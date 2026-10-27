<?php

namespace App\Support\Files;

use App\Support\Setup\Requirements;
use Imagick;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Prepara una foto per l'archivio (macchine, segnalazioni, ordini di lavoro):
 * - la raddrizza secondo l'orientamento dello scatto;
 * - lato lungo al massimo 1600 px, più una miniatura da 400 px per gli elenchi;
 * - la salva in WebP (qualità 80), o in JPEG se GD/Imagick del server non
 *   sanno scrivere WebP: la ricodifica toglie tutti i metadati, GPS compreso.
 *
 * Senza GD né Imagick la foto resta com'è (ma senza metadati se è un JPEG)
 * e la miniatura manca. La pagina Sistema segnala entrambi i casi.
 */
class PhotoProcessor
{
    /** Formati accettati in ingresso */
    public const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public const QUALITY = 80;

    /** Oltre questa dimensione (in megapixel) la foto non si apre: servirebbe troppa memoria */
    public const MAX_MEGAPIXELS = 50;

    /**
     * Formato in cui il server salva le foto: "webp", "jpeg" (GD/Imagick senza WebP) o "none" (nessuna libreria).
     */
    public static function outputFormat(): string
    {
        $driver = Requirements::imageDriver();
        if ($driver === null) {
            return 'none';
        }
        // MANUTRACK_PHOTO_FORMAT=jpeg per salvare sempre in JPEG
        if (config('manutrack.attachments.format') === 'jpeg') {
            return 'jpeg';
        }
        if ($driver === 'imagick') {
            return Imagick::queryFormats('WEBP') !== [] ? 'webp' : 'jpeg';
        }

        return function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false) ? 'webp' : 'jpeg';
    }

    /** HEIC/HEIF (iPhone): riconosciuto dall'intestazione "ftyp" */
    public static function isHeic(string $head): bool
    {
        return substr($head, 4, 4) === 'ftyp'
            && in_array(substr($head, 8, 4), ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1'], true);
    }

    /**
     * @return array{main: string, mime: string, ext: string, width: int|null, height: int|null, thumb: string|null, thumb_ext: string|null}
     */
    public function process(string $sourcePath): array
    {
        $head = (string) @file_get_contents($sourcePath, false, null, 0, 32);
        if (self::isHeic($head)) {
            throw PhotoException::heic();
        }

        $info = @getimagesize($sourcePath);
        $mime = $info['mime'] ?? '';
        if (! isset(self::MIMES[$mime])) {
            throw new PhotoException(__('assets.files.photo_invalid'));
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];
        if ($width * $height > self::MAX_MEGAPIXELS * 1_000_000) {
            throw new PhotoException(__('assets.files.photo_too_large', ['mp' => self::MAX_MEGAPIXELS]));
        }

        $format = self::outputFormat();
        $binary = (string) file_get_contents($sourcePath);

        if ($format === 'none') {
            return [
                'main' => $mime === 'image/jpeg' ? JpegMetadata::strip($binary) : $binary,
                'mime' => $mime,
                'ext' => self::MIMES[$mime],
                'width' => $width ?: null,
                'height' => $height ?: null,
                'thumb' => null,
                'thumb_ext' => null,
            ];
        }

        $this->raiseMemoryLimit($width, $height);
        $orientation = $mime === 'image/jpeg' ? JpegMetadata::orientation($binary) : 1;

        try {
            // L'orientamento si applica qui sotto: così funziona anche senza l'estensione "exif"
            $manager = Requirements::imageDriver() === 'imagick'
                ? ImageManager::imagick(autoOrientation: false, strip: true)
                : ImageManager::gd(autoOrientation: false, strip: true);
            $max = (int) config('manutrack.attachments.photo_max', 1600);
            $thumbMax = (int) config('manutrack.attachments.thumb_max', 400);

            $image = $this->orient($manager->read($binary), $orientation)->scaleDown($max, $max);
            $main = $this->encode($image, $format, self::QUALITY);
            [$outWidth, $outHeight] = [$image->width(), $image->height()];
            $thumb = $this->encode($image->scaleDown($thumbMax, $thumbMax), $format, 75);
        } catch (Throwable $e) {
            throw new PhotoException(__('assets.files.photo_unreadable'), previous: $e);
        }

        $ext = $format === 'webp' ? 'webp' : 'jpg';

        return [
            'main' => $main,
            'mime' => $format === 'webp' ? 'image/webp' : 'image/jpeg',
            'ext' => $ext,
            'width' => $outWidth,
            'height' => $outHeight,
            'thumb' => $thumb,
            'thumb_ext' => $ext,
        ];
    }

    private function encode(ImageInterface $image, string $format, int $quality): string
    {
        return $format === 'webp'
            ? (string) $image->toWebp(quality: $quality, strip: true)
            // Le trasparenze dei PNG diventano bianco: il JPEG non le supporta
            : (string) $image->toJpeg(quality: $quality + 2, progressive: true, strip: true);
    }

    /** Raddrizza secondo l'orientamento EXIF (stessa tabella di Intervention) */
    private function orient(ImageInterface $image, int $orientation): ImageInterface
    {
        return match ($orientation) {
            2 => $image->flop(),
            3 => $image->rotate(180),
            4 => $image->rotate(180)->flop(),
            5 => $image->rotate(270)->flop(),
            6 => $image->rotate(270),
            7 => $image->rotate(90)->flop(),
            8 => $image->rotate(90),
            default => $image,
        };
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
            @ini_set('memory_limit', (string) min(max($needed, 256 * 1024 * 1024), 768 * 1024 * 1024));
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
