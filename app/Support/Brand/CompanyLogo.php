<?php

namespace App\Support\Brand;

use App\Support\Setup\Requirements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

/**
 * Logo dell'azienda cliente: caricato dall'amministratore, mai incluso nel codice.
 *
 * Il file è salvato sul disco privato e servito da una rotta dedicata. Sono
 * accettati solo PNG, JPEG e WebP (niente SVG, che può contenere script);
 * se è disponibile GD o Imagick l'immagine viene ricodificata e ridimensionata,
 * il che elimina anche eventuali dati nascosti.
 */
class CompanyLogo
{
    public const SETTING = 'company.logo';

    /** Versione facoltativa per il tema scuro (es. scritta chiara) */
    public const SETTING_DARK = 'company.logo_dark';

    public const LIGHT = 'chiaro';

    public const DARK = 'scuro';

    public const MAX_WIDTH = 720;

    public const MAX_HEIGHT = 240;

    private const ALLOWED = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public static function disk(): string
    {
        return 'local';
    }

    /**
     * @return array{path: string, v: string, width: int, height: int, mime: string}|null
     */
    public static function current(string $variant = self::LIGHT): ?array
    {
        $value = settings(self::key($variant));

        return is_array($value) && isset($value['path'], $value['v']) ? $value : null;
    }

    public static function url(string $variant = self::LIGHT): ?string
    {
        $logo = self::current($variant);

        return $logo ? route('branding.logo', ['tema' => $variant, 'v' => $logo['v']]) : null;
    }

    private static function key(string $variant): string
    {
        return $variant === self::DARK ? self::SETTING_DARK : self::SETTING;
    }

    /**
     * Salva un nuovo logo e restituisce i dati salvati nelle impostazioni.
     *
     * @return array{path: string, v: string, width: int, height: int, mime: string}
     */
    public static function store(UploadedFile $file, string $variant = self::LIGHT): array
    {
        $info = @getimagesize($file->getRealPath());
        $mime = $info['mime'] ?? '';
        if (! isset(self::ALLOWED[$mime])) {
            throw new RuntimeException(__('settings.logo.invalid'));
        }

        $binary = (string) file_get_contents($file->getRealPath());
        [$width, $height] = [(int) $info[0], (int) $info[1]];
        $ext = self::ALLOWED[$mime];

        if (Requirements::imageDriver() !== null) {
            try {
                $manager = Requirements::imageDriver() === 'imagick' ? ImageManager::imagick() : ImageManager::gd();
                $image = $manager->read($binary)->scaleDown(self::MAX_WIDTH, self::MAX_HEIGHT);
                $binary = (string) $image->toPng();
                [$width, $height, $mime, $ext] = [$image->width(), $image->height(), 'image/png', 'png'];
            } catch (Throwable $e) {
                throw new RuntimeException(__('settings.logo.unreadable'), previous: $e);
            }
        }

        $path = 'branding/logo-'.Str::random(12).'.'.$ext;
        Storage::disk(self::disk())->put($path, $binary);

        self::deleteFile($variant);
        $data = ['path' => $path, 'v' => substr(sha1($binary), 0, 12), 'width' => $width, 'height' => $height, 'mime' => $mime];
        settings()->set(self::key($variant), $data);

        return $data;
    }

    public static function remove(string $variant = self::LIGHT): void
    {
        self::deleteFile($variant);
        settings()->forget(self::key($variant));
    }

    private static function deleteFile(string $variant): void
    {
        $logo = self::current($variant);
        if ($logo) {
            Storage::disk(self::disk())->delete($logo['path']);
        }
    }

    /**
     * Colori principali del logo (per proporre la palette): il più scuro e il
     * più chiaro tra quelli saturi, esclusi bianchi, neri e grigi.
     *
     * @return array{accent: string, soft: string}|null
     */
    public static function sampleColors(): ?array
    {
        $logo = self::current();
        if (! $logo || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $img = @imagecreatefromstring((string) Storage::disk(self::disk())->get($logo['path']));
        if ($img === false) {
            return null;
        }

        $small = imagecreatetruecolor(64, 64);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagecopyresampled($small, $img, 0, 0, 0, 0, 64, 64, imagesx($img), imagesy($img));

        // Raggruppa i pixel per tonalità (24 settori) e luminosità (scuro / chiaro)
        $buckets = [];
        for ($y = 0; $y < 64; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $c = imagecolorat($small, $x, $y);
                if ((($c >> 24) & 0x7F) > 60) {
                    continue; // trasparente
                }
                $hex = Color::fromRgb(($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF);
                [$h, $s, $l] = Color::hsl($hex);
                if ($s < 0.18 || $l < 0.08 || $l > 0.94) {
                    continue; // grigi, neri, bianchi
                }
                $key = (int) floor($h / 15).'-'.($l < 0.45 ? 'd' : 'l');
                $buckets[$key][] = [$h, $s, $l];
            }
        }

        if ($buckets === []) {
            return null;
        }

        uasort($buckets, fn ($a, $b) => count($b) <=> count($a));
        $groups = array_slice($buckets, 0, 4, true);
        $colors = array_map(function (array $px) {
            $n = count($px);

            return Color::fromHsl(array_sum(array_column($px, 0)) / $n, array_sum(array_column($px, 1)) / $n, array_sum(array_column($px, 2)) / $n);
        }, $groups);

        usort($colors, fn ($a, $b) => Color::hsl($a)[2] <=> Color::hsl($b)[2]);

        return ['accent' => $colors[0], 'soft' => end($colors)];
    }
}
