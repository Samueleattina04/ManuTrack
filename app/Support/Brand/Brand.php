<?php

namespace App\Support\Brand;

use Illuminate\Support\Facades\Storage;

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

    public const CUSTOM = 'custom';

    public const CUSTOM_SETTING = 'company.custom_palette';

    /** Impostazione con la versione delle icone generate per la palette personalizzata */
    public const CUSTOM_ICONS_SETTING = 'company.custom_icons';

    /** Nomi dei file delle icone dell'app */
    public const ICON_FILES = ['icon-192.png', 'icon-512.png', 'maskable-192.png', 'maskable-512.png', 'apple-touch-icon.png', 'favicon-32.png', 'favicon.svg'];

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
     * Palette attiva: una preimpostazione oppure quella personalizzata dall'azienda.
     *
     * @return array<string, string>
     */
    public static function palette(): array
    {
        $palettes = (array) config('manutrack.palettes');
        $key = (string) settings('company.palette', 'rame');

        if ($key === self::CUSTOM) {
            $custom = settings(self::CUSTOM_SETTING);
            if (is_array($custom) && isset($custom['accent_l'], $custom['logo_light_d'])) {
                return $custom;
            }
        }

        return $palettes[$key] ?? $palettes['rame'];
    }

    public static function paletteKey(): string
    {
        $key = (string) settings('company.palette', 'rame');

        if ($key === self::CUSTOM && is_array(settings(self::CUSTOM_SETTING))) {
            return self::CUSTOM;
        }

        return array_key_exists($key, (array) config('manutrack.palettes')) ? $key : 'rame';
    }

    /**
     * Indirizzo di un'icona dell'app per la palette attiva. Per la palette
     * personalizzata si usano le icone generate (se c'è GD), altrimenti
     * quelle della preimpostazione più vicina.
     */
    public static function iconUrl(string $file): string
    {
        $key = self::paletteKey();

        if ($key === self::CUSTOM) {
            $version = settings(self::CUSTOM_ICONS_SETTING);
            if (is_string($version) && Storage::disk('local')->exists("branding/icons/{$version}/{$file}")) {
                return route('branding.icon', ['version' => $version, 'file' => $file]);
            }

            $key = PaletteBuilder::nearestPreset(self::palette());
        }

        return asset("icons/{$key}/{$file}");
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
