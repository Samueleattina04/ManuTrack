<?php

namespace App\Actions\Maintenance;

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Tempi di lavoro: un solo cronometro acceso per persona (avviandone uno si
 * ferma quello su un altro ordine) e inserimenti a mano.
 */
class WorkTimer
{
    public function start(WorkOrder $order, User $user): TimeEntry
    {
        $running = TimeEntry::query()->running()->where('user_id', $user->id)->get();
        $same = $running->firstWhere('work_order_id', $order->id);
        if ($same) {
            return $same;
        }
        $running->each(fn (TimeEntry $t) => $t->stop());

        return $order->timeEntries()->create(['user_id' => $user->id, 'started_at' => now()]);
    }

    public function stop(WorkOrder $order, User $user): void
    {
        $order->timeEntries()->running()->where('user_id', $user->id)->get()->each(fn (TimeEntry $t) => $t->stop());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function add(WorkOrder $order, User $user, array $input): TimeEntry
    {
        $data = Validator::make($input, [
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['minutes' => __('maintenance.time.minutes'), 'date' => __('maintenance.time.date')])->validate();

        // Oggi: il lavoro finisce adesso; un giorno passato: alle 17
        $day = Carbon::parse($data['date']);
        $end = $day->isToday() ? now() : $day->setTime(17, 0);

        return $order->timeEntries()->create([
            'user_id' => $user->id,
            'started_at' => $end->copy()->subMinutes((int) $data['minutes']),
            'ended_at' => $end,
            'minutes' => (int) $data['minutes'],
            'note' => $data['note'] ?? null,
        ]);
    }
}
