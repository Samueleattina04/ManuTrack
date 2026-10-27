<?php

namespace Database\Seeders;

use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\DowntimeEvent;
use App\Models\FailureCode;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

/**
 * Sei mesi di storico inventato (guasti, fermi, ordini correttivi chiusi con
 * tempi e costi), così la dashboard degli indicatori ha qualcosa da mostrare.
 * Sempre gli stessi dati: il generatore casuale parte da un seme fisso.
 */
class DemoHistorySeeder extends Seeder
{
    public function run(): void
    {
        if (! DatabaseSeeder::demoAllowed() || ! Asset::query()->exists() || DowntimeEvent::query()->where('note', 'storico demo')->exists()) {
            return;
        }

        mt_srand(20260101);
        $tech = User::query()->where('email', 'tecnico1@example.com')->first();
        $manager = User::query()->where('email', 'responsabile@example.com')->first();
        $codes = FailureCode::query()->get()->groupBy(fn (FailureCode $c) => $c->kind->value);
        $rate = (float) settings('costs.hourly_rate', 0) ?: 38.5;
        $problems = ['Perdita di olio', 'Rumore anomalo', 'Allarme sul pannello', 'Non si avvia', 'Sensore guasto', 'Cinghia rotta'];

        // codice macchina => guasti in sei mesi
        foreach (['PRS-01' => 6, 'PRS-02' => 9, 'PRS-03' => 3, 'ROB-01' => 4, 'NAS-01' => 5, 'NAS-02' => 3, 'CMP-01' => 4] as $code => $failures) {
            $asset = Asset::query()->where('code', $code)->first();
            if (! $asset) {
                continue;
            }
            // Le macchine hanno uno storico di almeno sei mesi
            if ($asset->installed_on === null || $asset->installed_on->gt(now()->subMonths(7))) {
                $asset->forceFill(['installed_on' => now()->subYears(3)->toDateString()])->save();
            }
            for ($i = 0; $i < $failures; $i++) {
                $start = now()->subDays(mt_rand(5, 180))->setTime(mt_rand(6, 20), mt_rand(0, 3) * 15);
                $minutes = mt_rand(2, 24) * 15;
                $end = $start->copy()->addMinutes($minutes);
                $title = $problems[mt_rand(0, count($problems) - 1)];

                $order = new WorkOrder(['type' => 'correttivo', 'title' => $title, 'asset_id' => $asset->id, 'priority' => mt_rand(0, 3) === 0 ? 'alta' : 'media', 'external_cost' => mt_rand(0, 4) === 0 ? mt_rand(5, 40) * 10 : null]);
                $order->created_at = $start;
                $order->created_by = $manager?->id;
                $order->save();
                $order->forceFill([
                    'status' => WorkOrderStatus::Closed, 'started_at' => $start, 'completed_at' => $end, 'completed_by' => $tech?->id,
                    'closed_at' => $end->copy()->addHours(2), 'closed_by' => $manager?->id, 'labor_rate' => $rate,
                    'completion_notes' => 'Intervento eseguito (storico dimostrativo).',
                    'failure_problem_id' => $codes->get('problema')?->random()?->id,
                    'failure_cause_id' => $codes->get('causa')?->random()?->id,
                    'failure_remedy_id' => $codes->get('rimedio')?->random()?->id,
                ])->save();
                $order->statusChanges()->create(['from' => null, 'to' => WorkOrderStatus::Open, 'user_id' => $manager?->id]);
                $order->statusChanges()->create(['from' => WorkOrderStatus::Open, 'to' => WorkOrderStatus::Closed, 'user_id' => $manager?->id]);
                $order->timeEntries()->create(['user_id' => $tech?->id, 'started_at' => $start, 'ended_at' => $end, 'minutes' => max(15, $minutes - 15)]);
                DowntimeEvent::query()->create(['asset_id' => $asset->id, 'work_order_id' => $order->id, 'started_at' => $start, 'ended_at' => $end, 'note' => 'storico demo']);
            }
        }
    }
}
