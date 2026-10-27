<?php

namespace App\Support\Kpi;

use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Rapporto mensile per la direzione (docs/SPECIFICA.md §39): gli indicatori del
 * mese con le regole di docs/KPI.md, gli ordini conclusi nel mese, e la
 * situazione di adesso di ricambi sotto scorta e piani in ritardo.
 * Ogni parte compare solo a chi ha il permesso di vederla.
 */
final class MonthlyReport
{
    /** Ordini conclusi elencati per intero; oltre, il rapporto dice quanti altri ce ne sono */
    public const MAX_ORDERS = 150;

    public function __construct(public readonly CarbonImmutable $month, private User $user) {}

    /**
     * Mese nel formato AAAA-MM; vuoto = mese precedente. Non si accettano mesi futuri.
     *
     * @throws InvalidArgumentException
     */
    public static function monthFrom(?string $value, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now();
        if ($value === null || $value === '') {
            return $now->startOfMonth()->subMonthNoOverflow();
        }
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) ? CarbonImmutable::createFromFormat('!Y-m', $value, config('app.timezone')) : false;
        if (! $month) {
            throw new InvalidArgumentException(__('kpi.report.bad_month'));
        }
        if ($month->gt($now)) {
            throw new InvalidArgumentException(__('kpi.errors.future'));
        }

        return $month->startOfMonth();
    }

    public function period(): KpiPeriod
    {
        return KpiPeriod::between($this->month->toDateString(), $this->month->endOfMonth()->toDateString());
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $kpi = new KpiCalculator($this->period());
        $costs = $this->user->can('work_orders.view_costs');

        $completed = $kpi->completedOrders()->with(['asset', 'timeEntries', ...($costs ? ['stockMovements'] : [])])->orderBy('completed_at');
        $ordersTotal = (clone $completed)->count();

        return [
            'month' => $this->month,
            'period' => $kpi->period(),
            'downtime' => $kpi->downtime(),
            'mttr' => $kpi->mttr(),
            'mtbf' => $kpi->mtbf(),
            'backlog' => $kpi->backlog(),
            'mix' => $kpi->mix(),
            'compliance' => $kpi->compliance(),
            'costs' => $costs ? $kpi->costs() : null,
            'topAssets' => array_slice(array_values(array_filter($kpi->byAsset(), fn ($r) => $r['downtime']['count'] > 0)), 0, 5),
            'orders' => $this->user->can('work_orders.view') ? $completed->limit(self::MAX_ORDERS)->get() : null,
            'ordersTotal' => $ordersTotal,
            'lowStock' => $this->user->can('inventory.view') ? $this->lowStock() : null,
            'latePlans' => $this->user->can('preventive.view') ? $this->latePlans() : null,
            'canCosts' => $costs,
            'generatedAt' => CarbonImmutable::now(),
        ];
    }

    /** @return Collection<int, Part> */
    private function lowStock(): Collection
    {
        return Part::query()->where('is_active', true)->belowMinimum()->orderBy('code')->get();
    }

    /**
     * Piani attivi scaduti adesso, dal più in ritardo.
     *
     * @return Collection<int, MaintenancePlan>
     */
    private function latePlans(): Collection
    {
        return MaintenancePlan::query()->where('is_active', true)->with(['asset', 'meter', 'currentOrder'])->get()
            ->filter(fn (MaintenancePlan $p) => $p->dueState() === 'scaduto')
            ->sortBy(fn (MaintenancePlan $p) => $p->next_due_on?->getTimestamp() ?? PHP_INT_MAX)
            ->values();
    }

    /** Indirizzo del rapporto di un mese (per l'avviso del 1° del mese) */
    public static function url(CarbonImmutable $month): string
    {
        return route('kpi.report', ['mese' => $month->format('Y-m')], false);
    }
}
