<?php

use App\Models\Asset;
use App\Models\MaintenancePlan;

function makePlan(array $attributes = []): MaintenancePlan
{
    $plan = new MaintenancePlan(array_merge([
        'title' => 'Ingrassaggio guide',
        'asset_id' => $attributes['asset_id'] ?? Asset::factory()->create()->id,
        'trigger' => 'tempo',
        'schedule_mode' => 'fisso',
        'interval_value' => 1,
        'interval_unit' => 'mesi',
        'lead_days' => 7,
        'priority' => 'media',
    ], $attributes));
    $plan->start_on = $attributes['start_on'] ?? $attributes['next_due_on'] ?? today()->addDays(30)->toDateString();
    $plan->next_due_on = $attributes['next_due_on'] ?? $plan->start_on;
    $plan->next_meter_due = $attributes['next_meter_due'] ?? null;
    $plan->save();

    return $plan->refresh();
}
