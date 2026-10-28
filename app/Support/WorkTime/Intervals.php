<?php

namespace App\Support\WorkTime;

/**
 * Operazioni su intervalli di tempo [inizio, fine) espressi in timestamp (secondi).
 */
final class Intervals
{
    /**
     * Ordina e unisce gli intervalli che si toccano o si sovrappongono.
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     * @return list<array{0: int, 1: int}>
     */
    public static function merge(array $intervals): array
    {
        $intervals = array_values(array_filter($intervals, fn ($i) => $i[1] > $i[0]));
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        $out = [];
        foreach ($intervals as [$s, $e]) {
            $last = count($out) - 1;
            if ($last >= 0 && $s <= $out[$last][1]) {
                $out[$last][1] = max($out[$last][1], $e);
            } else {
                $out[] = [$s, $e];
            }
        }

        return $out;
    }

    /**
     * Intervalli tagliati a [from, to).
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     * @return list<array{0: int, 1: int}>
     */
    public static function clip(array $intervals, int $from, int $to): array
    {
        $out = [];
        foreach ($intervals as [$s, $e]) {
            $s = max($s, $from);
            $e = min($e, $to);
            if ($e > $s) {
                $out[] = [$s, $e];
            }
        }

        return $out;
    }

    /** @param  list<array{0: int, 1: int}>  $intervals */
    public static function seconds(array $intervals): int
    {
        return array_sum(array_map(fn ($i) => $i[1] - $i[0], $intervals));
    }

    /**
     * Secondi di [from, to) che cadono dentro gli intervalli (già uniti).
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    public static function overlap(array $intervals, int $from, int $to): int
    {
        $total = 0;
        foreach ($intervals as [$s, $e]) {
            if ($e <= $from) {
                continue;
            }
            if ($s >= $to) {
                break;
            }
            $total += min($e, $to) - max($s, $from);
        }

        return $total;
    }
}
