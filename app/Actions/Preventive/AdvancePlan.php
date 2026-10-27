<?php

namespace App\Actions\Preventive;

use App\Enums\WorkOrderStatus;
use App\Models\MaintenancePlan;
use App\Models\WorkOrder;
use App\Support\Preventive\PlanSchedule;
use Carbon\CarbonImmutable;

/**
 * Quando l'ordine di un piano si completa (o si annulla), il piano passa
 * alla scadenza successiva e torna libero di generare.
 * Si chiama dentro la transazione del cambio di stato.
 */
class AdvancePlan
{
    public function __construct(private PlanSchedule $schedule) {}

    public function handle(WorkOrder $order, WorkOrderStatus $to): void
    {
        if (! $order->maintenance_plan_id || ! in_array($to, [WorkOrderStatus::Completed, WorkOrderStatus::Closed, WorkOrderStatus::Cancelled], true)) {
            return;
        }

        /** @var MaintenancePlan|null $plan */
        $plan = MaintenancePlan::query()->lockForUpdate()->with('meter')->find($order->maintenance_plan_id);
        // Solo l'ordine in corso del piano lo fa avanzare (non un ordine vecchio corretto o riaperto)
        if (! $plan || $plan->current_work_order_id !== $order->id) {
            return;
        }

        $skipped = $to === WorkOrderStatus::Cancelled;
        $doneAt = $order->completed_at ?? now();
        $fill = ['current_work_order_id' => null];

        if ($plan->isTime()) {
            $fill['next_due_on'] = ($skipped
                ? $this->schedule->nextAfterSkip($plan, $order->plan_due_on, now())
                : $this->schedule->nextAfterDone($plan, $order->plan_due_on, $doneAt))->toDateString();
        } elseif ($plan->meter) {
            $fill['next_meter_due'] = $this->schedule->nextMeterDue($plan, (float) $plan->meter->current_value);
        }
        if (! $skipped) {
            $fill['last_done_at'] = CarbonImmutable::parse($doneAt);
        }

        $plan->forceFill($fill)->save();
    }
}
