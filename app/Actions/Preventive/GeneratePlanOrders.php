<?php

namespace App\Actions\Preventive;

use App\Actions\Maintenance\AssignWorkOrder;
use App\Actions\Maintenance\CreateWorkOrder;
use App\Enums\WorkOrderType;
use App\Models\MaintenancePlan;
use App\Models\Meter;
use App\Models\Part;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Audit\Audit;
use App\Support\Preventive\PlanSchedule;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Genera gli ordini di lavoro preventivi dei piani in scadenza.
 *
 * - Gira dal cron (ogni 15 minuti), dopo ogni lettura di un contatore e con
 *   "Genera ora" dalla scheda del piano.
 * - Al massimo un ordine aperto per piano: la riga del piano è bloccata
 *   mentre si controlla e si crea l'ordine, così due esecuzioni contemporanee
 *   non ne creano due.
 * - Assegnatario del piano avvisato come per ogni assegnazione; senza
 *   assegnatario si avvisa chi pianifica e assegna.
 */
class GeneratePlanOrders
{
    public function __construct(
        private CreateWorkOrder $create,
        private AssignWorkOrder $assign,
        private PlanSchedule $schedule,
        private PushDispatcher $push,
    ) {}

    /**
     * @return list<array{plan: MaintenancePlan, order: WorkOrder|null}> ordini creati (o da creare, in prova)
     */
    public function run(?CarbonInterface $now = null, bool $dryRun = false): array
    {
        $today = CarbonImmutable::parse(($now ?? now())->toDateString());
        $plans = MaintenancePlan::query()->active()->with(['meter', 'currentOrder', 'asset'])->orderBy('id')->get()
            ->filter(fn (MaintenancePlan $p) => $this->schedule->isDue($p, $today));

        $out = [];
        foreach ($plans as $plan) {
            if ($dryRun) {
                $out[] = ['plan' => $plan, 'order' => null];

                continue;
            }
            try {
                $order = $this->generate($plan, null, $today);
            } catch (Throwable $e) {
                // Un piano con dati non più validi non blocca gli altri
                report($e);

                continue;
            }
            if ($order) {
                $out[] = ['plan' => $plan, 'order' => $order];
            }
        }

        return $out;
    }

    /**
     * Piani a contatore di un contatore appena letto.
     *
     * @return list<WorkOrder>
     */
    public function forMeter(Meter $meter): array
    {
        $out = [];
        $today = CarbonImmutable::today();
        MaintenancePlan::query()->active()->where('meter_id', $meter->id)->with(['meter', 'currentOrder', 'asset'])->get()
            ->filter(fn (MaintenancePlan $p) => $this->schedule->isDue($p, $today))
            ->each(function (MaintenancePlan $p) use (&$out, $today): void {
                if ($order = $this->generate($p, null, $today)) {
                    $out[] = $order;
                }
            });

        return $out;
    }

    /**
     * Crea l'ordine del piano. Con $force ("Genera ora") anche prima dell'anticipo.
     * Restituisce null se c'è già un ordine aperto o se il piano non è in scadenza.
     */
    public function generate(MaintenancePlan $plan, ?User $actor, ?CarbonInterface $today = null, bool $force = false): ?WorkOrder
    {
        $today = CarbonImmutable::parse(($today ?? now())->toDateString());

        $order = DB::transaction(function () use ($plan, $actor, $today, $force) {
            /** @var MaintenancePlan $locked */
            $locked = MaintenancePlan::query()->lockForUpdate()->with(['meter', 'currentOrder'])->findOrFail($plan->id);
            if (! $locked->is_active || $locked->hasOpenOrder()) {
                return null;
            }
            if (! $force && ! $this->schedule->isDue($locked, $today)) {
                return null;
            }

            [$dueOn, $note] = $this->dueFor($locked, $today);
            $order = $this->create->handle($actor, [
                'type' => WorkOrderType::Preventive->value,
                'title' => $locked->title,
                'description' => trim(($locked->description ?? '')."\n\n".$note) ?: null,
                'asset_id' => $locked->asset_id,
                'priority' => $locked->priority->value,
                'due_at' => $dueOn->toDateString(),
                'checklist_template_id' => $locked->checklist_template_id,
            ], ['maintenance_plan_id' => $locked->id, 'plan_due_on' => $dueOn->toDateString()]);

            $locked->forceFill(['current_work_order_id' => $order->id, 'last_generated_at' => now()])->save();

            return $order;
        });

        if (! $order) {
            return null;
        }

        Audit::log('preventive.generated', $plan, $plan->title.' → '.$order->number, [], $actor);
        $this->assignOrWarn($plan->refresh(), $order, $actor);

        return $order->refresh();
    }

    /**
     * Scadenza dell'ordine e riga che spiega da dove viene.
     *
     * @return array{0: CarbonImmutable, 1: string}
     */
    private function dueFor(MaintenancePlan $plan, CarbonImmutable $today): array
    {
        if ($plan->isTime()) {
            $due = CarbonImmutable::parse(($plan->next_due_on ?? $today)->toDateString());

            return [$due, __('preventive.order_note_time', ['frequency' => $plan->frequencyLabel(), 'date' => $due->translatedFormat('j F Y')])];
        }

        $meter = $plan->meter;
        $threshold = (float) $plan->next_meter_due;
        // Data stimata dal consumo medio; senza letture sufficienti, l'anticipo del piano
        $due = ($meter ? $this->schedule->estimateDate($meter, $threshold) : null) ?? $today->addDays(max(1, $plan->lead_days));
        $due = $due->lt($today) ? $today : $due;

        return [$due, __('preventive.order_note_meter', [
            'frequency' => $plan->frequencyLabel(),
            'threshold' => Part::formatQty($threshold),
            'unit' => $meter->unit ?? '',
            'current' => Part::formatQty($meter->current_value ?? 0),
        ])];
    }

    private function assignOrWarn(MaintenancePlan $plan, WorkOrder $order, ?User $actor): void
    {
        if ($plan->assignee_id) {
            try {
                $this->assign->handle($order, [$plan->assignee_id], $actor);

                return;
            } catch (ValidationException) {
                // L'assegnatario non può più eseguire ordini: si avvisa chi pianifica
            }
        }

        $planners = User::permission('work_orders.assign')->where('is_active', true)
            ->when($actor, fn ($q) => $q->whereKeyNot($actor->id))->get();
        $this->push->send($planners, new PushMessage(
            title: __('preventive.push.generated', ['number' => $order->number]),
            body: trim(($order->asset ? $order->asset->code.' · ' : '').$order->title.' · '.__('preventive.push.to_assign')),
            url: route('work-orders.show', $order, absolute: false),
            tag: 'wo-'.$order->id,
        ));
    }
}
