<?php

namespace App\Support\Export;

/**
 * Protezione dalle formule nei fogli esportati (CSV injection, OWASP).
 *
 * Un testo che comincia con = + - @ (anche nella forma a larghezza piena), con
 * tabulazione, a capo o ritorno carrello, anche dopo spazi iniziali, verrebbe
 * letto come formula da Excel o LibreOffice. Lo si fa precedere da un apostrofo,
 * così resta testo. Vale per CSV ed Excel: un .xlsx risalvato come CSV non deve
 * riportare in vita la formula. I numeri e le date scritti da ManuTrack non sono
 * testi e non passano di qui.
 */
final class SafeCell
{
    private const TRIGGERS = ['=', '+', '-', '@', "\t", "\r", "\n", '＝', '＋', '－', '＠'];

    public static function text(?string $value): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        if (in_array($value[0], ["\t", "\r", "\n"], true)) {
            return "'".$value;
        }
        $trimmed = ltrim($value, " \u{00A0}");
        foreach (self::TRIGGERS as $trigger) {
            if (str_starts_with($trimmed, $trigger)) {
                return "'".$value;
            }
        }

        return $value;
    }
}
