<?php

namespace App\Support\Preventive;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Rispetto dei piani: per gli ordini preventivi con scadenza nel periodo
 * (e già scaduta), quanti sono stati completati entro la scadenza.
 *
 * - in tempo: completato entro la fine del giorno di scadenza;
 * - in ritardo: completato dopo;
 * - saltato: annullato;
 * - scaduto aperto: non ancora completato e scadenza passata.
 * Rispetto = in tempo / (in tempo + in ritardo + saltati + scaduti aperti).
 */
class PlanCompliance
{
    /**
     * @param  list<int>|null  $assetIds  solo gli ordini di queste macchine (null = tutte)
     * @return array{on_time: int, late: int, skipped: int, overdue: int, total: int, rate: float|null}
     */
    public function summary(CarbonInterface $from, CarbonInterface $to, ?int $planId = null, ?array $assetIds = null): array
    {
        return $this->count($this->orders($from, $to, $planId, $assetIds));
    }

    /**
     * Per piano, per la tabella dell'elenco.
     *
     * @return Collection<int, array{on_time: int, late: int, skipped: int, overdue: int, total: int, rate: float|null}>
     */
    public function byPlan(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->orders($from, $to, null)->groupBy('maintenance_plan_id')->map(fn (Collection $orders) => $this->count($orders));
    }

    /** Esito di un singolo ordine del piano: on_time, late, skipped, overdue, pending */
    public static function outcome(WorkOrder $order, ?CarbonInterface $today = null): string
    {
        $today = CarbonImmutable::parse(($today ?? today())->toDateString());
        $due = $order->plan_due_on ? CarbonImmutable::parse($order->plan_due_on->toDateString())->endOfDay() : null;

        return match (true) {
            $order->status === WorkOrderStatus::Cancelled => 'skipped',
            $order->completed_at !== null && in_array($order->status, [WorkOrderStatus::Completed, WorkOrderStatus::Closed], true) => $due === null || $order->completed_at->lte($due) ? 'on_time' : 'late',
            $due !== null && $due->lt($today) => 'overdue',
            default => 'pending',
        };
    }

    /**
     * @param  list<int>|null  $assetIds
     * @return Collection<int, WorkOrder>
     */
    private function orders(CarbonInterface $from, CarbonInterface $to, ?int $planId, ?array $assetIds = null): Collection
    {
        $to = CarbonImmutable::parse($to->toDateString())->min(CarbonImmutable::today());

        return WorkOrder::query()
            ->whereNotNull('maintenance_plan_id')
            ->when($planId, fn ($q) => $q->where('maintenance_plan_id', $planId))
            ->when($assetIds !== null, fn ($q) => $q->whereIn('asset_id', $assetIds))
            ->whereBetween('plan_due_on', [$from->toDateString(), $to->toDateString()])
            ->get(['id', 'maintenance_plan_id', 'status', 'plan_due_on', 'completed_at']);
    }

    /**
     * @param  Collection<int, WorkOrder>  $orders
     * @return array{on_time: int, late: int, skipped: int, overdue: int, total: int, rate: float|null}
     */
    private function count(Collection $orders): array
    {
        $counts = ['on_time' => 0, 'late' => 0, 'skipped' => 0, 'overdue' => 0];
        foreach ($orders as $order) {
            $outcome = self::outcome($order);
            if (isset($counts[$outcome])) {
                $counts[$outcome]++;
            }
        }
        $total = array_sum($counts);

        return $counts + ['total' => $total, 'rate' => $total > 0 ? round($counts['on_time'] / $total * 100, 1) : null];
    }
}
