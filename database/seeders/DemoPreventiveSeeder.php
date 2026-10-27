<?php

namespace Database\Seeders;

use App\Actions\Preventive\GeneratePlanOrders;
use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\ChecklistTemplate;
use App\Models\MaintenancePlan;
use App\Models\Meter;
use App\Models\Part;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

/**
 * Piani preventivi di prova, tutti inventati, con un po' di storico per il
 * rispetto dei piani: interventi in tempo, uno in ritardo e uno saltato.
 */
class DemoPreventiveSeeder extends Seeder
{
    public function run(): void
    {
        if (! DatabaseSeeder::demoAllowed() || MaintenancePlan::query()->exists() || ! Asset::query()->exists()) {
            return;
        }

        $asset = fn (string $code) => Asset::query()->where('code', $code)->firstOrFail();
        $meter = fn (string $code) => Meter::query()->whereHas('asset', fn ($q) => $q->where('code', $code))->firstOrFail();
        $tech1 = User::query()->where('email', 'tecnico1@example.com')->first();
        $tech2 = User::query()->where('email', 'tecnico2@example.com')->first();
        $manager = User::query()->where('email', 'responsabile@example.com')->first() ?? User::query()->firstOrFail();

        $monthly = ChecklistTemplate::query()->firstOrCreate(['name' => 'Ispezione mensile pressa']);
        if (! $monthly->items()->exists()) {
            foreach ([['Livello olio centralina', 'spunta', null], ['Ingrassaggio guide e colonne', 'spunta', null], ['Pressione di esercizio', 'valore', 'bar'], ['Prova dei dispositivi di sicurezza', 'spunta', null]] as $i => [$label, $kind, $unit]) {
                $monthly->items()->create(['label' => $label, 'kind' => $kind, 'unit' => $unit, 'required' => true, 'position' => $i + 1]);
            }
        }

        $plan = function (array $a, array $extra = []) use ($manager): MaintenancePlan {
            $p = new MaintenancePlan($a);
            foreach ($extra as $k => $v) {
                $p->{$k} = $v;
            }
            $p->created_by = $manager->id;
            $p->save();

            return $p;
        };

        $today = today();
        $presses = $plan(['title' => 'Ispezione mensile e ingrassaggio', 'description' => "Controllare livelli, ingrassare guide e colonne, provare barriere e pulsanti di emergenza.\nAnnotare la pressione di esercizio.", 'asset_id' => $asset('PRS-01')->id, 'trigger' => 'tempo', 'schedule_mode' => 'fisso', 'interval_value' => 1, 'interval_unit' => 'mesi', 'lead_days' => 7, 'priority' => 'media', 'assignee_id' => $tech1?->id, 'estimated_minutes' => 90, 'checklist_template_id' => $monthly->id],
            ['start_on' => $today->copy()->addDays(5)->subMonths(6)->toDateString(), 'next_due_on' => $today->copy()->addDays(5)->toDateString()]);
        $plan(['title' => 'Ispezione mensile e ingrassaggio', 'asset_id' => $asset('PRS-03')->id, 'trigger' => 'tempo', 'schedule_mode' => 'fisso', 'interval_value' => 1, 'interval_unit' => 'mesi', 'lead_days' => 7, 'priority' => 'media', 'assignee_id' => $tech1?->id, 'estimated_minutes' => 90, 'checklist_template_id' => $monthly->id],
            ['start_on' => $today->copy()->addDays(19)->toDateString(), 'next_due_on' => $today->copy()->addDays(19)->toDateString()]);
        $filter = $plan(['title' => 'Sostituzione filtro olio centralina', 'asset_id' => $asset('PRS-01-CEN')->id, 'trigger' => 'contatore', 'schedule_mode' => 'fisso', 'meter_id' => $meter('PRS-01-CEN')->id, 'meter_interval' => 500, 'meter_lead' => 50, 'lead_days' => 7, 'priority' => 'alta', 'assignee_id' => $tech1?->id, 'estimated_minutes' => 45],
            ['next_meter_due' => 17150]);
        $plan(['title' => 'Tagliando compressore (2.000 h)', 'description' => 'Cambio olio, filtro aria, filtro olio e separatore; controllo cinghia.', 'asset_id' => $asset('CMP-01')->id, 'trigger' => 'contatore', 'schedule_mode' => 'fisso', 'meter_id' => $meter('CMP-01')->id, 'meter_interval' => 2000, 'meter_lead' => 100, 'lead_days' => 7, 'priority' => 'media', 'assignee_id' => $tech1?->id, 'estimated_minutes' => 180],
            ['next_meter_due' => 32000]);
        $plan(['title' => 'Termografia del quadro elettrico', 'asset_id' => $asset('QEL-01')->id, 'trigger' => 'tempo', 'schedule_mode' => 'fisso', 'interval_value' => 1, 'interval_unit' => 'anni', 'lead_days' => 30, 'priority' => 'alta', 'assignee_id' => $tech2?->id, 'estimated_minutes' => 120],
            ['start_on' => $today->copy()->addMonths(4)->startOfMonth()->toDateString(), 'next_due_on' => $today->copy()->addMonths(4)->startOfMonth()->toDateString()]);
        $robot = $plan(['title' => 'Controllo e lubrificazione assi robot', 'asset_id' => $asset('ROB-02')->id, 'trigger' => 'tempo', 'schedule_mode' => 'ultima', 'interval_value' => 3, 'interval_unit' => 'mesi', 'lead_days' => 10, 'priority' => 'media', 'assignee_id' => $tech2?->id, 'estimated_minutes' => 60],
            ['start_on' => $today->copy()->addDays(40)->toDateString(), 'next_due_on' => $today->copy()->addDays(40)->toDateString()]);
        $belt = $plan(['title' => 'Tensionamento e pulizia nastro', 'asset_id' => $asset('NAS-02')->id, 'trigger' => 'tempo', 'schedule_mode' => 'fisso', 'interval_value' => 2, 'interval_unit' => 'settimane', 'lead_days' => 2, 'priority' => 'bassa', 'estimated_minutes' => 30],
            ['start_on' => $today->copy()->subDays(2)->subWeeks(10)->toDateString(), 'next_due_on' => $today->copy()->subDays(2)->toDateString()]);

        // Ricambi previsti, se il magazzino dimostrativo c'è
        $part = fn (string $code) => Part::query()->where('code', $code)->value('id');
        if ($id = $part('FIL-OLIO10')) {
            $filter->parts()->attach($id, ['quantity' => 1]);
        }
        if ($id = $part('OLIO-ISO46')) {
            $filter->parts()->attach($id, ['quantity' => 5]);
        }
        if ($id = $part('GRA-EP2')) {
            $presses->parts()->attach($id, ['quantity' => 0.5]);
            $robot->parts()->attach($id, ['quantity' => 0.2]);
        }

        // Storico degli ultimi mesi: in tempo, uno in ritardo, uno saltato
        $history = [
            [$presses, 5, 4, 'chiuso'], [$presses, 4, 3, 'chiuso'], [$presses, 3, -2, 'chiuso'], [$presses, 2, 1, 'chiuso'], [$presses, 1, 0, 'chiuso'],
            [$belt, 6, 0, 'chiuso'], [$belt, 4, 1, 'chiuso'], [$belt, 2, 0, 'annullato'],
        ];
        foreach ($history as [$p, $monthsAgo, $daysEarly, $status]) {
            $due = $p->interval_unit?->value === 'settimane' ? $today->copy()->subWeeks($monthsAgo * 2)->subDays(2) : $today->copy()->addDays(5)->subMonths($monthsAgo);
            $order = new WorkOrder(['type' => 'preventivo', 'title' => $p->title, 'asset_id' => $p->asset_id, 'priority' => $p->priority->value, 'due_at' => $due->toDateString()]);
            $order->maintenance_plan_id = $p->id;
            $order->plan_due_on = $due->toDateString();
            $order->created_by = null;
            $order->save();
            $done = $due->copy()->subDays($daysEarly)->setTime(10, 30);
            $order->forceFill($status === 'annullato'
                ? ['status' => WorkOrderStatus::Cancelled]
                : ['status' => WorkOrderStatus::Closed, 'started_at' => $done->copy()->subHour(), 'completed_at' => $done, 'completed_by' => $p->assignee_id, 'closed_at' => $done->copy()->addDay(), 'closed_by' => $manager->id, 'completion_notes' => 'Eseguito come da piano.', 'labor_rate' => (float) settings('costs.hourly_rate', 0)])->save();
            $order->statusChanges()->create(['from' => null, 'to' => WorkOrderStatus::Open, 'user_id' => null]);
            $order->statusChanges()->create(['from' => WorkOrderStatus::Open, 'to' => $order->status, 'user_id' => $manager->id, 'note' => $status === 'annullato' ? 'Linea ferma per inventario' : null]);
            if ($status !== 'annullato') {
                $p->forceFill(['last_done_at' => $done])->save();
            }
        }

        // Ordini dei piani già in scadenza (pressa entro l'anticipo, filtro alla soglia, nastro scaduto)
        app(GeneratePlanOrders::class)->run();
    }
}
