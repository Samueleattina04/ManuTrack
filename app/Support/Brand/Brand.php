<?php

namespace App\Support\Brand;

/**
 * Geometria del logo ManuTrack e colori dell'azienda.
 *
 * Le coordinate della M sono ricalcate da public/images/logo-originale.png:
 * unità = pixel del file 1200×1200, origine nell'angolo in alto a sinistra della M.
 */
class Brand
{
    /** Faccia sinistra (chiara) */
    public const FACE_LIGHT = [[0, 0], [203, 202], [203, 339], [90, 227], [86, 377], [0, 292]];

    /** Faccia destra (scura) */
    public const FACE_DARK = [[406, 0], [203, 202], [203, 339], [316, 227], [320, 377], [406, 292]];

    /** Ombra portata verso il basso a destra */
    public const SHADOW = [[406, 0], [496, 90], [496, 382], [410, 467], [320, 377], [406, 292]];

    public const MARK_WIDTH = 406;

    public const MARK_HEIGHT = 378;

    /**
     * @param  list<array{0: int|float, 1: int|float}>  $points
     */
    public static function points(array $points): string
    {
        return implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $points));
    }

    /**
     * Tracciato della scritta, generato da scripts/brand/build-wordmark.mjs.
     *
     * @return array{d: string, box: array{left: int|float, right: int|float, top: int|float, baseline: int|float}}|null
     */
    public static function wordmark(): ?array
    {
        static $cache = false;

        if ($cache === false) {
            $file = resource_path('brand/wordmark.json');
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $cache = is_array($data) && isset($data['d'], $data['box']) ? $data : null;
        }

        return $cache;
    }

    /**
     * Palette attiva dell'azienda, con le varianti per tema scuro e chiaro.
     *
     * @return array<string, string>
     */
    public static function palette(): array
    {
        $palettes = (array) config('manutrack.palettes');
        $key = (string) settings('company.palette', 'rame');

        return $palettes[$key] ?? $palettes['rame'];
    }

    public static function paletteKey(): string
    {
        $key = (string) settings('company.palette', 'rame');

        return array_key_exists($key, (array) config('manutrack.palettes')) ? $key : 'rame';
    }

    /**
     * Variabili CSS della palette, da inserire nella pagina.
     */
    public static function cssVariables(): string
    {
        $p = self::palette();

        $map = [
            '--acc-d' => $p['accent_d'], '--acc2-d' => $p['accent2_d'], '--on-acc-d' => $p['on_accent_d'],
            '--acc-l' => $p['accent_l'], '--acc2-l' => $p['accent2_l'], '--on-acc-l' => $p['on_accent_l'],
            '--acc-fg-d' => $p['accent_fg_d'] ?? $p['accent_d'], '--acc-fg-l' => $p['accent_fg_l'] ?? $p['accent_l'],
            '--acc-soft-d' => $p['soft_d'] ?? $p['accent_d'], '--acc-soft-l' => $p['soft_l'] ?? $p['accent_l'],
            '--logo-lc-d' => $p['logo_light_d'], '--logo-ls-d' => $p['logo_dark_d'],
            '--logo-lc-l' => $p['logo_light_l'], '--logo-ls-l' => $p['logo_dark_l'],
        ];

        $css = '';
        foreach ($map as $name => $value) {
            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
                $css .= $name.':'.$value.';';
            }
        }

        return ':root{'.$css.'}';
    }
}
