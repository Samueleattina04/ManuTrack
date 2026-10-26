<?php

namespace App\Support\Brand;

/**
 * Costruisce una palette completa (tema scuro e chiaro) partendo dal colore
 * dell'azienda, garantendo i contrasti WCAG AA:
 * - pulsanti: testo bianco su accento ≥ 4,5:1 in entrambi i temi;
 * - testi d'accento: ≥ 7:1 sul fondo scuro e ≥ 4,5:1 sul fondo chiaro.
 */
final class PaletteBuilder
{
    private const WHITE = '#FFFFFF';

    private const DARK_SURFACE = '#151A21';

    private const LIGHT_BG = '#EDEFF2';

    /**
     * @param  string  $accent  colore principale (es. il verde scuro del logo)
     * @param  string|null  $soft  colore secondario chiaro (es. il pistacchio), facoltativo
     * @return array<string, string>
     */
    public static function build(string $accent, ?string $soft = null, string $label = 'Personalizzata'): array
    {
        $accent = Color::normalize($accent);
        [$h, $s] = Color::hsl($accent);
        $soft = $soft ? Color::normalize($soft) : Color::fromHsl($h, min(0.55, max($s, 0.35)), 0.62);

        // Accento per pulsanti: abbastanza scuro per il testo bianco
        $accentLight = Color::untilContrast($accent, self::WHITE, 4.6, -1);
        $accentLight = Color::untilContrast($accentLight, self::LIGHT_BG, 4.5, -1);

        // Nel tema scuro: la versione più luminosa che regge ancora il testo bianco
        [$ah, $as, $al] = Color::hsl($accentLight);
        $accentDark = $accentLight;
        for ($l = $al; $l <= 0.9; $l += 0.01) {
            $candidate = Color::fromHsl($ah, $as, $l);
            if (Color::contrast($candidate, self::WHITE) < 4.6) {
                break;
            }
            $accentDark = $candidate;
        }

        $lighter = fn (string $c, float $d) => Color::withLightness($c, min(0.95, Color::hsl($c)[2] + $d));
        $accent2Dark = Color::contrast($lighter($accentDark, 0.03), self::WHITE) >= 4.5 ? $lighter($accentDark, 0.03) : $accentDark;
        $accent2Light = Color::contrast($lighter($accentLight, 0.05), self::WHITE) >= 4.5 ? $lighter($accentLight, 0.05) : $accentLight;

        [$sh, $ss, $sl] = Color::hsl($soft);

        return [
            'label' => $label,
            'accent_d' => $accentDark,
            'accent2_d' => $accent2Dark,
            'on_accent_d' => self::WHITE,
            'accent_l' => $accentLight,
            'accent2_l' => $accent2Light,
            'on_accent_l' => self::WHITE,
            'accent_fg_d' => Color::untilContrast($soft, self::DARK_SURFACE, 7.0, +1),
            'accent_fg_l' => $accentLight,
            'soft_d' => $soft,
            'soft_l' => Color::fromHsl($sh, $ss, max(0.35, $sl - 0.1)),
            'logo_light_d' => $soft,
            'logo_dark_d' => Color::fromHsl($ah, max($as, 0.3), 0.22),
            'logo_light_l' => Color::fromHsl($sh, $ss, max(0.35, $sl - 0.1)),
            'logo_dark_l' => Color::fromHsl($ah, max($as, 0.3), 0.16),
        ];
    }

    /**
     * Preimpostazione più vicina a una palette (per le icone quando manca GD).
     *
     * @param  array<string, string>  $palette
     */
    public static function nearestPreset(array $palette): string
    {
        $best = 'rame';
        $bestDistance = INF;
        foreach ((array) config('manutrack.palettes') as $key => $preset) {
            $d = Color::distance($preset['logo_light_d'], $palette['logo_light_d']) + Color::distance($preset['accent_l'], $palette['accent_l']);
            if ($d < $bestDistance) {
                $bestDistance = $d;
                $best = (string) $key;
            }
        }

        return $best;
    }
}
