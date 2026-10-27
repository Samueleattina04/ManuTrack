<?php

namespace App\Actions\Maintenance;

use App\Actions\Assets\ChangeAssetStatus;
use App\Enums\AssetStatus;
use App\Enums\WaitingReason;
use App\Enums\WorkOrderStatus;
use App\Models\DowntimeEvent;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sposta un ordine di lavoro nel ciclo di vita, con gli effetti collegati:
 *
 * - in corso: data di inizio, cronometro di chi inizia, macchina "in manutenzione" se era ferma;
 * - in attesa: motivo obbligatorio, cronometri fermati;
 * - completato: checklist obbligatoria completa, note, codici guasto, cronometri fermati,
 *   macchina di nuovo operativa (a scelta) e fermo chiuso;
 * - chiuso: il costo orario del momento resta fissato sull'ordine;
 * - riaperto (da completato a in corso);
 * - annullato: motivo obbligatorio.
 *
 * Il permesso lo controlla chi chiama (policy "transition"); qui si controlla
 * sempre che il passaggio sia ammesso dal ciclo di vita.
 */
class TransitionWorkOrder
{
    public function __construct(private ChangeAssetStatus $assetStatus, private Notify $notify) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(WorkOrder $order, WorkOrderStatus $to, User $actor, array $input = []): WorkOrder
    {
        $from = $order->status;
        if (! $from->canMoveTo($to)) {
            throw ValidationException::withMessages(['status' => __('maintenance.errors.transition', ['from' => $from->label(), 'to' => $to->label()])]);
        }

        $data = Validator::make($input, $this->rules($from, $to), [], [
            'note' => __('maintenance.field.note'),
            'waiting_reason' => __('maintenance.field.waiting_reason'),
            'completion_notes' => __('maintenance.field.completion_notes'),
        ])->validate();

        if ($to === WorkOrderStatus::Completed && ! $order->loadMissing('checklist')->checklistComplete()) {
            throw ValidationException::withMessages(['checklist' => __('maintenance.errors.checklist_incomplete')]);
        }

        DB::transaction(function () use ($order, $from, $to, $actor, $data): void {
            $now = now();
            $fill = ['status' => $to];

            switch ($to) {
                case WorkOrderStatus::InProgress:
                    $fill['waiting_reason'] = null;
                    $fill['started_at'] = $order->started_at ?? $now;
                    if ($from === WorkOrderStatus::Completed) {
                        $fill += ['completed_at' => null, 'completed_by' => null, 'reopened_count' => $order->reopened_count + 1];
                    }
                    break;
                case WorkOrderStatus::Waiting:
                    $fill['waiting_reason'] = $data['waiting_reason'];
                    break;
                case WorkOrderStatus::Completed:
                    $fill += [
                        'completed_at' => $now,
                        'completed_by' => $actor->id,
                        'completion_notes' => trim((string) $data['completion_notes']),
                        'failure_problem_id' => $data['failure_problem_id'] ?? $order->failure_problem_id,
                        'failure_cause_id' => $data['failure_cause_id'] ?? $order->failure_cause_id,
                        'failure_remedy_id' => $data['failure_remedy_id'] ?? $order->failure_remedy_id,
                    ];
                    break;
                case WorkOrderStatus::Closed:
                    $fill += ['closed_at' => $now, 'closed_by' => $actor->id, 'labor_rate' => (float) settings('costs.hourly_rate', 0)];
                    break;
                default:
                    break;
            }

            $order->forceFill($fill)->save();
            $order->statusChanges()->create([
                'from' => $from,
                'to' => $to,
                'user_id' => $actor->id,
                'note' => $data['note'] ?? ($to === WorkOrderStatus::Waiting ? WaitingReason::from($data['waiting_reason'])->label() : null),
            ]);

            // Cronometri: fermi quando il lavoro si interrompe
            if (in_array($to, [WorkOrderStatus::Waiting, WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)) {
                $order->timeEntries()->running()->get()->each(fn (TimeEntry $t) => $t->stop($now));
            }
        });

        $this->sideEffects($order, $from, $to, $actor, $data);
        Audit::log('work_orders.status_changed', $order, $order->number.' · '.$from->label().' → '.$to->label(), ['status' => [$from->value, $to->value]], $actor);

        return $order->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(WorkOrderStatus $from, WorkOrderStatus $to): array
    {
        $code = fn (string $kind) => ['nullable', 'integer', Rule::exists('failure_codes', 'id')->where('kind', $kind)];

        return match ($to) {
            WorkOrderStatus::Waiting => ['waiting_reason' => ['required', Rule::enum(WaitingReason::class)], 'note' => ['nullable', 'string', 'max:500']],
            WorkOrderStatus::Completed => [
                'completion_notes' => ['required', 'string', 'min:3', 'max:5000'],
                'failure_problem_id' => $code('problema'),
                'failure_cause_id' => $code('causa'),
                'failure_remedy_id' => $code('rimedio'),
                'asset_operational' => ['boolean'],
            ],
            WorkOrderStatus::Cancelled => ['note' => ['required', 'string', 'min:3', 'max:500']],
            WorkOrderStatus::InProgress => $from === WorkOrderStatus::Completed
                ? ['note' => ['required', 'string', 'min:3', 'max:500']]
                : ['note' => ['nullable', 'string', 'max:500']],
            default => ['note' => ['nullable', 'string', 'max:500']],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sideEffects(WorkOrder $order, WorkOrderStatus $from, WorkOrderStatus $to, User $actor, array $data): void
    {
        $asset = $order->asset;

        if ($to === WorkOrderStatus::InProgress && in_array($from, [WorkOrderStatus::Open, WorkOrderStatus::Planned], true)) {
            // Chi inizia fa partire il proprio cronometro
            app(WorkTimer::class)->start($order, $actor);
            if ($asset && in_array($asset->status, [AssetStatus::Fault, AssetStatus::Stopped], true)) {
                $this->assetStatus->handle($asset, AssetStatus::Maintenance, $actor, $order->number, notify: false);
            }
        }

        if ($to === WorkOrderStatus::Completed) {
            if ($asset && ($data['asset_operational'] ?? true) && $asset->status !== AssetStatus::Operational && $asset->status !== AssetStatus::Retired) {
                $this->assetStatus->handle($asset, AssetStatus::Operational, $actor, $order->number);
                DowntimeEvent::query()->where('asset_id', $asset->id)->open()
                    ->update(['ended_at' => now(), 'work_order_id' => DB::raw('coalesce(work_order_id, '.(int) $order->id.')')]);
            }
            $this->notify->completed($order->loadMissing('request.user', 'asset'), $actor);
        }
    }
}
