<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\User;
use App\Models\WorkRequest;

/**
 * Segnalazioni: chi le fa vede sempre le proprie; tutte le altre solo con
 * "Vedere tutte le segnalazioni". Valutarle richiede "Accettare o respingere".
 */
class WorkRequestPolicy
{
    public function view(User $user, WorkRequest $request): bool
    {
        return $request->user_id === $user->id || $user->can('requests.view_all');
    }

    public function create(User $user): bool
    {
        return $user->can('requests.create');
    }

    public function triage(User $user, WorkRequest $request): bool
    {
        return $user->can('requests.triage') && $request->status === RequestStatus::New;
    }
}
