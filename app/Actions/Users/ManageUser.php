<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\Access\Access;
use App\Support\Audit\Audit;
use Illuminate\Validation\ValidationException;

/**
 * Operazioni dell'amministratore sugli account, con le protezioni necessarie:
 * non si può togliere l'ultimo amministratore attivo, né disattivare se stessi.
 *
 * Il permesso "users.manage" si può dare anche ad altri ruoli: chi non è amministratore
 * gestisce le persone ma non gli amministratori, non nomina amministratori e non cambia il
 * proprio ruolo (altrimenti si darebbe da solo tutti i permessi).
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
        self::ensureMayManage($actor, $user, $role);
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
        self::ensureMayManage($actor, $user);
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
        self::ensureMayManage($actor, $user);
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        Audit::log('users.2fa_reset', $user, $user->email, actor: $actor);
    }

    /**
     * Chi non è amministratore non tocca gli amministratori né il proprio account, e non
     * assegna il ruolo di amministratore.
     */
    public static function ensureMayManage(User $actor, ?User $user, ?string $role = null): void
    {
        if ($actor->isAdmin()) {
            return;
        }
        if ($role === Access::ADMIN || ($user && ($user->isAdmin() || ($role !== null && $actor->is($user))))) {
            throw ValidationException::withMessages(['user' => __('users.admin_only')]);
        }
    }

    private function ensureAnotherAdmin(User $user): void
    {
        $others = User::role(Access::ADMIN)->active()->whereKeyNot($user->id)->count();
        if ($others === 0) {
            throw ValidationException::withMessages(['user' => __('users.last_admin')]);
        }
    }
}
