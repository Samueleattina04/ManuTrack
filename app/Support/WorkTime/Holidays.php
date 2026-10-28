<?php

namespace App\Support\WorkTime;

use Carbon\CarbonImmutable;

/**
 * Festività nazionali italiane di un anno, proposte nei calendari di lavoro e modificabili.
 * Il santo patrono e le chiusure aziendali si inseriscono a mano.
 */
final class Holidays
{
    /**
     * @return array<string, string> data (AAAA-MM-GG) => nome
     */
    public static function italian(int $year): array
    {
        $easter = self::easter($year);
        $days = [
            sprintf('%04d-01-01', $year) => 'Capodanno',
            sprintf('%04d-01-06', $year) => 'Epifania',
            $easter->toDateString() => 'Pasqua',
            $easter->addDay()->toDateString() => 'Lunedì dell\'Angelo',
            sprintf('%04d-04-25', $year) => 'Festa della Liberazione',
            sprintf('%04d-05-01', $year) => 'Festa del Lavoro',
            sprintf('%04d-06-02', $year) => 'Festa della Repubblica',
            sprintf('%04d-08-15', $year) => 'Ferragosto',
            sprintf('%04d-11-01', $year) => 'Ognissanti',
            sprintf('%04d-12-08', $year) => 'Immacolata Concezione',
            sprintf('%04d-12-25', $year) => 'Natale',
            sprintf('%04d-12-26', $year) => 'Santo Stefano',
        ];
        // San Francesco d'Assisi, patrono d'Italia: di nuovo festa nazionale dal 2026
        if ($year >= 2026) {
            $days[sprintf('%04d-10-04', $year)] = 'San Francesco d\'Assisi';
        }
        ksort($days);

        return $days;
    }

    /** Domenica di Pasqua (calendario gregoriano, algoritmo di Meeus/Jones/Butcher) */
    public static function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, config('app.timezone'));
    }
}
