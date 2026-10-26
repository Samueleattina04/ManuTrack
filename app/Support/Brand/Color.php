<?php

namespace App\Support\Brand;

use InvalidArgumentException;

/**
 * Piccole utilità sui colori: conversioni, luminanza e contrasto WCAG.
 */
final class Color
{
    public static function normalize(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            throw new InvalidArgumentException("Colore non valido: {$hex}");
        }

        return '#'.strtoupper($hex);
    }

    public static function isValid(string $hex): bool
    {
        return (bool) preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($hex));
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $hex = ltrim(self::normalize($hex), '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    public static function fromRgb(float $r, float $g, float $b): string
    {
        $c = fn (float $v) => str_pad(dechex((int) max(0, min(255, round($v)))), 2, '0', STR_PAD_LEFT);

        return strtoupper('#'.$c($r).$c($g).$c($b));
    }

    /**
     * Tonalità (0–360), saturazione e luminosità (0–1).
     *
     * @return array{0: float, 1: float, 2: float}
     */
    public static function hsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn ($v) => $v / 255, self::rgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match (true) {
            $max === $r => fmod(($g - $b) / $d + ($g < $b ? 6 : 0), 6),
            $max === $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h * 60, $s, $l];
    }

    public static function fromHsl(float $h, float $s, float $l): string
    {
        $h = fmod(fmod($h, 360) + 360, 360) / 360;
        $s = max(0, min(1, $s));
        $l = max(0, min(1, $l));
        if ($s == 0.0) {
            return self::fromRgb($l * 255, $l * 255, $l * 255);
        }
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $f = function (float $t) use ($p, $q): float {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);

            return match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };
        };

        return self::fromRgb($f($h + 1 / 3) * 255, $f($h) * 255, $f($h - 1 / 3) * 255);
    }

    public static function withLightness(string $hex, float $l): string
    {
        [$h, $s] = self::hsl($hex);

        return self::fromHsl($h, $s, $l);
    }

    public static function luminance(string $hex): float
    {
        $c = array_map(function ($v) {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $x = self::luminance($a);
        $y = self::luminance($b);

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    /**
     * Scurisce o schiarisce (solo luminosità) finché il contrasto con $against
     * raggiunge $min. $direction: -1 scurisce, +1 schiarisce.
     */
    public static function untilContrast(string $hex, string $against, float $min, int $direction): string
    {
        [$h, $s, $l] = self::hsl($hex);
        $out = self::normalize($hex);
        for ($i = 0; $i < 100 && self::contrast($out, $against) < $min; $i++) {
            $l = max(0, min(1, $l + 0.01 * $direction));
            $out = self::fromHsl($h, $s, $l);
            if ($l <= 0 || $l >= 1) {
                break;
            }
        }

        return $out;
    }

    /**
     * Distanza percepita approssimata tra due colori (0 = uguali).
     */
    public static function distance(string $a, string $b): float
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);
        $rm = ($r1 + $r2) / 2;

        return sqrt((2 + $rm / 256) * ($r1 - $r2) ** 2 + 4 * ($g1 - $g2) ** 2 + (2 + (255 - $rm) / 256) * ($b1 - $b2) ** 2);
    }
}
