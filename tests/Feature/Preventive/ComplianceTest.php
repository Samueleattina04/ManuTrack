<?php

use App\Enums\WorkOrderStatus as S;
use App\Models\WorkOrder;
use App\Support\Preventive\PlanCompliance;

beforeEach(function () {
    installManuTrack();
    $this->plan = makePlan();
});

function planOrder(int $planId, string $due, S $status, ?string $completedAt = null): WorkOrder
{
    $o = new WorkOrder(['type' => 'preventivo', 'title' => 'Intervento', 'priority' => 'media', 'due_at' => $due]);
    $o->maintenance_plan_id = $planId;
    $o->plan_due_on = $due;
    $o->save();
    $o->forceFill(['status' => $status, 'completed_at' => $completedAt])->save();

    return $o;
}

it('conta in tempo, in ritardo, saltati e scaduti aperti', function () {
    $this->travelTo('2026-06-30 12:00');
    planOrder($this->plan->id, '2026-02-10', S::Closed, '2026-02-10 18:00');   // in tempo (stesso giorno)
    planOrder($this->plan->id, '2026-03-10', S::Completed, '2026-03-08 09:00'); // in tempo (prima)
    planOrder($this->plan->id, '2026-04-10', S::Closed, '2026-04-12 09:00');    // in ritardo
    planOrder($this->plan->id, '2026-05-10', S::Cancelled);                     // saltato
    planOrder($this->plan->id, '2026-06-10', S::InProgress);                    // scaduto aperto
    planOrder($this->plan->id, '2026-07-10', S::Planned);                       // non ancora dovuto
    planOrder($this->plan->id, '2025-01-10', S::Closed, '2025-01-10 10:00');    // fuori periodo

    $summary = app(PlanCompliance::class)->summary(today()->subMonths(12)->addDay(), today());
    expect($summary)->toBe(['on_time' => 2, 'late' => 1, 'skipped' => 1, 'overdue' => 1, 'total' => 5, 'rate' => 40.0]);
    expect(app(PlanCompliance::class)->byPlan(today()->subMonths(12), today())[$this->plan->id]['total'])->toBe(5);
});

it('senza interventi nel periodo il rispetto non è calcolabile', function () {
    expect(app(PlanCompliance::class)->summary(today()->subYear(), today())['rate'])->toBeNull();
});
