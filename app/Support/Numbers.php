<?php

namespace App\Support;

/**
 * Numeri scritti dalle persone: "1.234,5" (italiano), "1234.5", "1 234".
 */
final class Numbers
{
    public static function parse(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[\s\x{00A0}€]/u', '', (string) $value) ?? '';
        if ($clean === '') {
            return null;
        }
        if (str_contains($clean, ',')) {
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $clean)) {
            $clean = str_replace('.', '', $clean); // punti delle migliaia
        }

        return is_numeric($clean) ? (float) $clean : null;
    }
}
