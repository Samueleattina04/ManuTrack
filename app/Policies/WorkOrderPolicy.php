<?php

namespace App\Policies;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Access\Access;

/**
 * Chi può fare cosa su un ordine di lavoro.
 * (L'amministratore passa sempre: vedi Gate::before in AppServiceProvider.)
 *
 * - Vedere: "Vedere gli ordini di lavoro".
 * - Eseguire (iniziare, attese, checklist, tempi, foto, completare): "Eseguire gli
 *   ordini" e in più essere tra gli assegnatari, oppure poter assegnare.
 * - Prendere in carico un ordine senza assegnatari: "Eseguire gli ordini".
 * - Assegnare, pianificare, annullare, modificare: "Pianificare e assegnare".
 * - Chiudere e riaprire: "Chiudere e riaprire gli ordini".
 * - Correggere lo stato fuori dal ciclo: solo l'amministratore.
 */
class WorkOrderPolicy
{
    public function view(User $user, WorkOrder $order): bool
    {
        return $user->can('work_orders.view');
    }

    public function create(User $user): bool
    {
        return $user->can('work_orders.create');
    }

    public function execute(User $user, WorkOrder $order): bool
    {
        if (! $order->status->isActive() && $order->status !== WorkOrderStatus::Completed) {
            return false;
        }

        return $user->can('work_orders.execute') && ($order->isAssignedTo($user) || $user->can('work_orders.assign'));
    }

    public function take(User $user, WorkOrder $order): bool
    {
        return $user->can('work_orders.execute')
            && in_array($order->status, [WorkOrderStatus::Open, WorkOrderStatus::Planned], true)
            && ! $order->assignees()->exists();
    }

    public function assign(User $user, WorkOrder $order): bool
    {
        return $user->can('work_orders.assign') && $order->status->isActive();
    }

    public function update(User $user, WorkOrder $order): bool
    {
        return $user->can('work_orders.assign') && $order->status->isActive();
    }

    /** Passaggio a un altro stato: deve essere ammesso dal ciclo di vita e dal permesso giusto */
    public function transition(User $user, WorkOrder $order, WorkOrderStatus $to): bool
    {
        $permission = $order->status->transitions()[$to->value] ?? null;
        if ($permission === null || ! $user->can($permission)) {
            return false;
        }

        return $permission !== 'work_orders.execute' || $this->execute($user, $order);
    }

    /**
     * "Correggi stato": solo l'amministratore, in qualunque momento (anche dopo
     * la chiusura). Gli altri ruoli restano vincolati al ciclo normale, anche se
     * hanno tutti i permessi degli ordini.
     */
    public function correctStatus(User $user, WorkOrder $order): bool
    {
        return $user->hasRole(Access::ADMIN);
    }

    public function comment(User $user, WorkOrder $order): bool
    {
        return $user->can('work_orders.view')
            && ($user->can('work_orders.execute') || $user->can('work_orders.assign') || $user->can('work_orders.create'));
    }

    public function viewCosts(User $user, WorkOrder $order): bool
    {
        return $user->can('work_orders.view_costs');
    }
}
