<?php

namespace App\Actions\Maintenance;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Decide chi lavora su un ordine. Le persone aggiunte ricevono un avviso
 * (urgente se l'ordine è critico). Un ordine "aperto" con almeno un
 * assegnatario passa a "pianificato".
 */
class AssignWorkOrder
{
    public function __construct(private Notify $notify) {}

    /**
     * @param  list<int>  $userIds
     */
    public function handle(WorkOrder $order, array $userIds, User $actor): void
    {
        if (! $order->status->isActive()) {
            throw ValidationException::withMessages(['assignees' => __('maintenance.errors.not_active')]);
        }

        $users = User::query()->whereIn('id', $userIds)->where('is_active', true)->get();
        // Solo chi può eseguire gli ordini può esserne assegnatario
        $invalid = $users->reject(fn (User $u) => $u->can('work_orders.execute'));
        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages(['assignees' => __('maintenance.errors.cannot_execute', ['names' => $invalid->pluck('name')->implode(', ')])]);
        }

        $before = $order->assignees()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $added = $users->reject(fn (User $u) => in_array($u->id, $before, true))->values();

        DB::transaction(function () use ($order, $users, $actor): void {
            $order->assignees()->sync($users->mapWithKeys(fn (User $u) => [$u->id => ['created_at' => now()]])->all());

            if ($order->status === WorkOrderStatus::Open && $users->isNotEmpty()) {
                $order->forceFill(['status' => WorkOrderStatus::Planned])->save();
                $order->statusChanges()->create(['from' => WorkOrderStatus::Open, 'to' => WorkOrderStatus::Planned, 'user_id' => $actor->id]);
            }
        });

        $after = $users->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        sort($before);
        if ($before !== $after) {
            Audit::log('work_orders.assigned', $order, $order->number.' · '.($users->pluck('name')->implode(', ') ?: '—'), actor: $actor);
        }

        if ($added->isNotEmpty()) {
            $this->notify->assigned($order->loadMissing('asset'), $added, $actor);
        }
    }
}
