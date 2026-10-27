<?php

namespace App\Support\Preventive;

use App\Enums\PlanMode;
use App\Models\MaintenancePlan;
use App\Models\Meter;
use App\Models\MeterReading;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calcolo delle scadenze dei piani preventivi. Tutto in date (senza ore),
 * nel fuso dell'applicazione.
 *
 * A tempo:
 * - date fisse: le scadenze sono start_on + k × intervallo, sempre contate da
 *   start_on (31 gennaio mensile → 28 febbraio → 31 marzo, senza slittare);
 * - dall'ultima esecuzione: scadenza = data di completamento + intervallo.
 * A contatore:
 * - date fisse: soglie prima + k × intervallo (500, 1000, 1500 h…);
 * - dall'ultima esecuzione: valore del contatore al completamento + intervallo.
 *
 * Un intervento fatto in ritardo non genera una raffica di recuperi: la
 * scadenza successiva è la prima dopo il completamento.
 */
class PlanSchedule
{
    /** Limite di sicurezza per i cicli sulle occorrenze */
    private const MAX_STEPS = 5000;

    /** Prima scadenza fissa strettamente successiva a $after */
    public function occurrenceAfter(MaintenancePlan $plan, CarbonInterface $after): CarbonImmutable
    {
        $start = CarbonImmutable::parse(($plan->start_on ?? $plan->next_due_on ?? today())->toDateString());
        $after = CarbonImmutable::parse($after->toDateString());
        $n = max(1, (int) $plan->interval_value);
        $unit = $plan->interval_unit;
        if ($unit === null || $start->gt($after)) {
            return $start;
        }

        // Si salta subito vicino alla data, poi qualche passo esatto. La stima usa la
        // durata massima di mesi e anni (31 e 366 giorni), così non supera mai $after.
        $days = max(1, (int) $start->diffInDays($after));
        $approxDays = match ($unit->value) {
            'giorni' => $n, 'settimane' => 7 * $n, 'mesi' => 31 * $n, default => 366 * $n,
        };
        $k = max(0, intdiv($days, $approxDays) - 1);
        for ($i = 0; $i < self::MAX_STEPS; $i++, $k++) {
            $date = $unit->add($start, $k * $n);
            if ($date->gt($after)) {
                return $date;
            }
        }

        return $unit->add($after, $n);
    }

    /** Scadenza dopo un intervento completato il giorno $doneOn */
    public function nextAfterDone(MaintenancePlan $plan, ?CarbonInterface $plannedDue, CarbonInterface $doneOn): CarbonImmutable
    {
        $doneOn = CarbonImmutable::parse($doneOn->toDateString());
        if ($plan->schedule_mode === PlanMode::FromLast || $plan->interval_unit === null) {
            return $plan->interval_unit?->add($doneOn, max(1, (int) $plan->interval_value)) ?? $doneOn->addDay();
        }
        $from = $plannedDue && CarbonImmutable::parse($plannedDue->toDateString())->gt($doneOn)
            ? CarbonImmutable::parse($plannedDue->toDateString())
            : $doneOn;

        return $this->occurrenceAfter($plan, $from);
    }

    /**
     * Scadenza dopo un intervento saltato (ordine annullato): si conta dalla
     * scadenza prevista, mai prima di domani.
     */
    public function nextAfterSkip(MaintenancePlan $plan, ?CarbonInterface $plannedDue, CarbonInterface $today): CarbonImmutable
    {
        $today = CarbonImmutable::parse($today->toDateString());
        $due = $plannedDue ? CarbonImmutable::parse($plannedDue->toDateString()) : $today;
        $n = max(1, (int) $plan->interval_value);
        if ($plan->interval_unit === null) {
            return $today->addDay();
        }
        if ($plan->schedule_mode === PlanMode::Fixed) {
            return $this->occurrenceAfter($plan, $due->max($today));
        }
        $next = $plan->interval_unit->add($due, $n);
        for ($i = 0; $next->lte($today) && $i < self::MAX_STEPS; $i++) {
            $next = $plan->interval_unit->add($next, $n);
        }

        return $next;
    }

    /** Soglia del contatore dopo un intervento (fatto o saltato) con il contatore a $current */
    public function nextMeterDue(MaintenancePlan $plan, float $current): float
    {
        $interval = max(0.01, (float) $plan->meter_interval);
        if ($plan->schedule_mode === PlanMode::FromLast || $plan->next_meter_due === null) {
            return round($current + $interval, 2);
        }
        $next = (float) $plan->next_meter_due;
        if ($next > $current) {
            // Fatto in anticipo: si passa comunque alla soglia successiva
            return round($next + $interval, 2);
        }
        $steps = (int) floor(($current - $next) / $interval) + 1;

        return round($next + $steps * $interval, 2);
    }

    /** Il piano deve generare un ordine adesso? */
    public function isDue(MaintenancePlan $plan, CarbonInterface $today): bool
    {
        if (! $plan->is_active || $plan->hasOpenOrder()) {
            return false;
        }
        if ($plan->isTime()) {
            return $plan->next_due_on !== null
                && CarbonImmutable::parse($plan->next_due_on->toDateString())->subDays($plan->lead_days)->lte(CarbonImmutable::parse($today->toDateString()));
        }
        if ($plan->next_meter_due === null || ! $plan->meter) {
            return false;
        }

        return (float) $plan->meter->current_value >= (float) $plan->next_meter_due - (float) $plan->meter_lead;
    }

    /**
     * Consumo medio giornaliero del contatore negli ultimi 90 giorni
     * (null se le letture non bastano). Gli azzeramenti spezzano il conto.
     */
    public function dailyUsage(Meter $meter): ?float
    {
        $readings = $meter->readings()->where('read_at', '>=', now()->subDays(90))->reorder('read_at')->get(['value', 'read_at', 'is_reset']);
        $total = 0.0;
        $prev = null;
        foreach ($readings as $r) {
            /** @var MeterReading $r */
            if ($prev && ! $r->is_reset) {
                $total += max(0, (float) $r->value - (float) $prev->value);
            }
            $prev = $r;
        }
        $first = $readings->first();
        $last = $readings->last();
        if (! $first || ! $last || $total <= 0) {
            return null;
        }
        $days = $first->read_at->diffInHours($last->read_at) / 24;

        return $days >= 1 ? $total / $days : null;
    }

    /** Data stimata in cui il contatore raggiunge $value */
    public function estimateDate(Meter $meter, float $value): ?CarbonImmutable
    {
        $rate = $this->dailyUsage($meter);
        if ($rate === null) {
            return null;
        }
        $remaining = max(0, $value - (float) $meter->current_value);

        // Stima: al giorno più vicino (il cambio dell'ora legale non deve spostarla di un giorno)
        return CarbonImmutable::today()->addDays((int) round($remaining / $rate));
    }

    /**
     * Scadenze previste tra due date, per il calendario.
     *
     * @return list<array{date: CarbonImmutable, estimated: bool}>
     */
    public function projected(MaintenancePlan $plan, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::parse($from->toDateString());
        $to = CarbonImmutable::parse($to->toDateString());
        $out = [];

        if ($plan->isTime()) {
            if (! $plan->next_due_on || $plan->interval_unit === null) {
                return [];
            }
            $date = CarbonImmutable::parse($plan->next_due_on->toDateString());
            for ($i = 0; $date->lte($to) && $i < 400; $i++) {
                if ($date->gte($from)) {
                    $out[] = ['date' => $date, 'estimated' => false];
                }
                $date = $plan->schedule_mode === PlanMode::Fixed
                    ? $this->occurrenceAfter($plan, $date)
                    : $plan->interval_unit->add($date, max(1, (int) $plan->interval_value));
            }

            return $out;
        }

        if (! $plan->meter || $plan->next_meter_due === null) {
            return [];
        }
        $rate = $this->dailyUsage($plan->meter);
        if ($rate === null) {
            return [];
        }
        $value = (float) $plan->next_meter_due;
        for ($i = 0; $i < 400; $i++) {
            $date = CarbonImmutable::today()->addDays((int) round(max(0, $value - (float) $plan->meter->current_value) / $rate));
            if ($date->gt($to)) {
                break;
            }
            if ($date->gte($from)) {
                $out[] = ['date' => $date, 'estimated' => true];
            }
            $value += max(0.01, (float) $plan->meter_interval);
        }

        return $out;
    }
}
