<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\Access\Access;
use App\Support\Audit\Audit;
use Illuminate\Validation\ValidationException;

/**
 * Operazioni dell'amministratore sugli account, con le protezioni necessarie:
 * non si può togliere l'ultimo amministratore attivo, né disattivare se stessi.
 */
class ManageUser
{
    public function changeRole(User $actor, User $user, string $role): void
    {
        if (! in_array($role, Access::ROLES, true)) {
            throw ValidationException::withMessages(['role' => __('users.invite.invalid_role')]);
        }

        $previous = $user->roleName();
        if ($previous === $role) {
            return;
        }
        if ($previous === Access::ADMIN) {
            $this->ensureAnotherAdmin($user);
        }

        $user->syncRoles([$role]);
        Audit::log('users.role_changed', $user, $user->email, ['role' => [$previous, $role]], $actor);
    }

    public function setActive(User $actor, User $user, bool $active): void
    {
        if ($user->anonymized_at !== null) {
            throw ValidationException::withMessages(['user' => __('gdpr.anonymize.already')]);
        }
        if (! $active && $actor->is($user)) {
            throw ValidationException::withMessages(['user' => __('users.cannot_deactivate_self')]);
        }
        if (! $active && $user->isAdmin()) {
            $this->ensureAnotherAdmin($user);
        }

        $user->forceFill(['is_active' => $active, 'deactivated_at' => $active ? null : now()])->save();
        Audit::log($active ? 'users.activated' : 'users.deactivated', $user, $user->email, actor: $actor);
    }

    public function resetTwoFactor(User $actor, User $user): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        Audit::log('users.2fa_reset', $user, $user->email, actor: $actor);
    }

    private function ensureAnotherAdmin(User $user): void
    {
        $others = User::role(Access::ADMIN)->active()->whereKeyNot($user->id)->count();
        if ($others === 0) {
            throw ValidationException::withMessages(['user' => __('users.last_admin')]);
        }
    }
}
