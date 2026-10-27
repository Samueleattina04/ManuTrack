<?php

namespace App\Actions\Maintenance;

use App\Actions\Preventive\AdvancePlan;
use App\Enums\WaitingReason;
use App\Enums\WorkOrderStatus;
use App\Models\DowntimeEvent;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Audit\Audit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Correggi stato" (solo amministratore, controllato dalla policy correctStatus):
 * porta un ordine in qualunque stato, anche dopo la chiusura, fuori dal ciclo normale.
 *
 * - Motivo obbligatorio, scritto nello storico dell'ordine (segnato come correzione)
 *   e nel registro attività con i dati prima e dopo.
 * - I campi dell'ordine restano coerenti con il nuovo stato (date di completamento
 *   e chiusura, motivo d'attesa, cronometri).
 * - Fermi macchina collegati all'ordine (o alla sua segnalazione):
 *   "close" li chiude alla data di completamento, "reopen" riapre l'ultimo,
 *   "keep" li lascia; se cambia la data di completamento, i fermi che si erano
 *   chiusi con il completamento si spostano con lei.
 * Lo stato della macchina non cambia da solo: si corregge dalla sua scheda.
 */
class CorrectWorkOrderStatus
{
    public const DOWNTIME_MODES = ['keep', 'close', 'reopen'];

    /**
     * @param  array<string, mixed>  $input  to, reason, completed_at, waiting_reason, downtime
     */
    public function handle(WorkOrder $order, User $actor, array $input): WorkOrder
    {
        $data = Validator::make($input, [
            'to' => ['required', Rule::enum(WorkOrderStatus::class)],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'completed_at' => ['nullable', 'date', 'before_or_equal:now'],
            'waiting_reason' => [Rule::requiredIf(($input['to'] ?? null) === WorkOrderStatus::Waiting->value), 'nullable', Rule::enum(WaitingReason::class)],
            'downtime' => ['nullable', Rule::in(self::DOWNTIME_MODES)],
        ], [], [
            'reason' => __('maintenance.correction.reason'),
            'completed_at' => __('maintenance.correction.completed_at'),
            'waiting_reason' => __('maintenance.field.waiting_reason'),
        ])->validate();

        $from = $order->status;
        $to = WorkOrderStatus::from($data['to']);
        $terminal = in_array($to, [WorkOrderStatus::Completed, WorkOrderStatus::Closed], true);
        $oldCompleted = $order->completed_at;
        $newCompleted = $terminal
            ? (! empty($data['completed_at']) ? Carbon::parse($data['completed_at']) : ($oldCompleted ?? now()))
            : null;

        if ($from === $to && ! ($terminal && $newCompleted && ! $newCompleted->equalTo($oldCompleted))) {
            throw ValidationException::withMessages(['to' => __('maintenance.correction.same_status')]);
        }
        if ($newCompleted && $order->started_at && $newCompleted->isBefore($order->started_at)) {
            throw ValidationException::withMessages(['completed_at' => __('maintenance.correction.before_start')]);
        }

        $linked = $this->linkedDowntimes($order);
        $mode = $data['downtime'] ?? (in_array($to, [WorkOrderStatus::Completed, WorkOrderStatus::Closed, WorkOrderStatus::Cancelled], true) && $linked->contains(fn ($d) => $d->ended_at === null) ? 'close' : 'keep');
        $before = $order->only(['status', 'completed_at', 'closed_at', 'waiting_reason']);
        $downtimeLog = [];

        DB::transaction(function () use ($order, $actor, $data, $from, $to, $terminal, $newCompleted, $oldCompleted, $linked, $mode, &$downtimeLog): void {
            $fill = ['status' => $to, 'waiting_reason' => $to === WorkOrderStatus::Waiting ? $data['waiting_reason'] : null];

            if ($terminal) {
                $fill += ['completed_at' => $newCompleted, 'completed_by' => $order->completed_by ?? $actor->id];
                $fill += $to === WorkOrderStatus::Closed
                    ? ['closed_at' => $order->closed_at ?? now(), 'closed_by' => $order->closed_by ?? $actor->id, 'labor_rate' => $order->labor_rate ?? (float) settings('costs.hourly_rate', 0)]
                    : ['closed_at' => null, 'closed_by' => null, 'labor_rate' => null];
            } else {
                $fill += ['completed_at' => null, 'completed_by' => null, 'closed_at' => null, 'closed_by' => null, 'labor_rate' => null];
                if ($to === WorkOrderStatus::InProgress) {
                    $fill['started_at'] = $order->started_at ?? now();
                }
            }

            $order->forceFill($fill)->save();
            $order->statusChanges()->create([
                'from' => $from,
                'to' => $to,
                'user_id' => $actor->id,
                'note' => trim((string) $data['reason']),
                'is_correction' => true,
            ]);

            // Ordine di un piano portato a completato, chiuso o annullato: il piano avanza
            app(AdvancePlan::class)->handle($order, $to);

            // Il lavoro non è in corso: nessun cronometro resta acceso
            if ($to !== WorkOrderStatus::InProgress) {
                $order->timeEntries()->running()->get()->each(fn (TimeEntry $t) => $t->stop());
            }

            $downtimeLog = $this->adjustDowntimes($order, $linked, $mode, $newCompleted, $oldCompleted);
        });

        $after = $order->refresh()->only(['status', 'completed_at', 'closed_at', 'waiting_reason']);
        $changes = [];
        foreach ($before as $key => $value) {
            $old = $value instanceof \BackedEnum ? $value->value : ($value instanceof Carbon ? $value->toDateTimeString() : $value);
            $new = $after[$key] instanceof \BackedEnum ? $after[$key]->value : ($after[$key] instanceof Carbon ? $after[$key]->toDateTimeString() : $after[$key]);
            if ($old !== $new) {
                $changes[$key] = [$old, $new];
            }
        }
        if ($downtimeLog !== []) {
            $changes['downtime'] = $downtimeLog;
        }

        Audit::log('work_orders.status_corrected', $order, $order->number.' · '.$from->label().' → '.$to->label().' · '.trim((string) $data['reason']), $changes, $actor);

        return $order;
    }

    /**
     * Fermi dell'ordine o della sua segnalazione d'origine.
     *
     * @return Collection<int, DowntimeEvent>
     */
    public function linkedDowntimes(WorkOrder $order): Collection
    {
        $requestId = $order->request()->value('id');

        return DowntimeEvent::query()
            ->where(fn ($q) => $q->where('work_order_id', $order->id)->when($requestId, fn ($q) => $q->orWhere('work_request_id', $requestId)))
            ->orderBy('started_at')->get();
    }

    /**
     * @param  Collection<int, DowntimeEvent>  $linked
     * @return list<string>
     */
    private function adjustDowntimes(WorkOrder $order, Collection $linked, string $mode, ?Carbon $newCompleted, ?Carbon $oldCompleted): array
    {
        $log = [];
        $fmt = fn (?Carbon $d) => $d?->format('d/m/Y H:i') ?? '—';
        $set = function (DowntimeEvent $event, ?Carbon $end) use (&$log, $fmt, $order): void {
            if ($end !== null && $end->isBefore($event->started_at)) {
                $end = $event->started_at->copy();
            }
            $log[] = __('maintenance.correction.downtime_log', ['id' => $event->id, 'from' => $fmt($event->ended_at), 'to' => $fmt($end)]);
            $event->forceFill(['ended_at' => $end, 'work_order_id' => $event->work_order_id ?? $order->id])->save();
        };

        if ($mode === 'close') {
            foreach ($linked->whereNull('ended_at') as $event) {
                $set($event, $newCompleted ?? now());
            }
        } elseif ($mode === 'reopen') {
            $last = $linked->whereNotNull('ended_at')->sortByDesc('ended_at')->first();
            $assetBusy = $last && DowntimeEvent::query()->where('asset_id', $last->asset_id)->open()->exists();
            if ($last && ! $assetBusy) {
                $set($last, null);
            }
        }

        // La data di completamento si è spostata: si spostano i fermi chiusi con lei
        if ($newCompleted && $oldCompleted && ! $newCompleted->equalTo($oldCompleted)) {
            foreach ($linked as $event) {
                $event->refresh();
                if ($event->ended_at && abs($event->ended_at->diffInSeconds($oldCompleted)) <= 60) {
                    $set($event, $newCompleted->copy());
                }
            }
        }

        return $log;
    }
}
