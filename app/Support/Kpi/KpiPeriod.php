<?php

namespace App\Support\Kpi;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Periodo degli indicatori (docs/KPI.md §1.2): dal giorno "dal" al giorno "al"
 * compresi, in ora locale, cioè l'intervallo semiaperto [inizio, fine) con
 * fine = giorno dopo "al" alle 00:00, tagliata ad adesso se è nel futuro.
 */
final class KpiPeriod
{
    public const PRESETS = ['30g', 'mese', 'mese_prec', '3m', 'anno', '12m'];

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly CarbonImmutable $now,
    ) {}

    public static function between(string $from, string $to, ?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now();
        $fromDate = CarbonImmutable::createFromFormat('!Y-m-d', $from, config('app.timezone'));
        $toDate = CarbonImmutable::createFromFormat('!Y-m-d', $to, config('app.timezone'));
        if (! $fromDate || ! $toDate || $fromDate->format('Y-m-d') !== $from || $toDate->format('Y-m-d') !== $to) {
            throw new InvalidArgumentException(__('kpi.errors.date'));
        }
        if ($toDate->lt($fromDate)) {
            throw new InvalidArgumentException(__('kpi.errors.order'));
        }
        $start = $fromDate->startOfDay();
        if ($start->gt($now)) {
            throw new InvalidArgumentException(__('kpi.errors.future'));
        }
        $end = $toDate->addDay()->startOfDay();

        return new self($fromDate, $toDate, $start, $end->gt($now) ? $now : $end, $now);
    }

    /**
     * Periodo come lo indica l'indirizzo della dashboard (periodo, dal, al).
     *
     * @throws InvalidArgumentException date non valide
     */
    public static function fromInput(string $preset, string $from = '', string $to = '', ?CarbonImmutable $now = null): self
    {
        return $preset === 'scelta' && $from !== '' && $to !== ''
            ? self::between($from, $to, $now)
            : self::preset($preset, $now);
    }

    public static function preset(string $key, ?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now();
        $today = $now->startOfDay();
        [$from, $to] = match ($key) {
            'mese' => [$today->startOfMonth(), $today],
            'mese_prec' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            '3m' => [$today->subMonthsNoOverflow(3)->addDay(), $today],
            'anno' => [$today->startOfYear(), $today],
            '12m' => [$today->subYearNoOverflow()->addDay(), $today],
            default => [$today->subDays(29), $today], // ultimi 30 giorni, oggi compreso
        };

        return self::between($from->format('Y-m-d'), $to->format('Y-m-d'), $now);
    }

    /** Ore reali trascorse tra inizio e fine (l'ora legale conta: 23 o 25 ore) */
    public function hours(): float
    {
        return ($this->end->getTimestamp() - $this->start->getTimestamp()) / 3600;
    }

    /** Ultimo giorno che conta per le scadenze dei piani: "al", ma non oltre oggi */
    public function lastDueDay(): CarbonImmutable
    {
        $today = $this->now->startOfDay();

        return $this->to->gt($today) ? $today : $this->to;
    }
}
