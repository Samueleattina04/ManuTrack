<?php

namespace App\Actions\Users;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Support\Access\Access;
use App\Support\Audit\Audit;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crea un invito (o lo rinnova, se per la stessa email ce n'è già uno aperto)
 * e lo invia per email. Il codice in chiaro esiste solo nel messaggio.
 */
class InviteUser
{
    public function handle(User $inviter, string $email, string $role, ?string $name = null): Invitation
    {
        $email = mb_strtolower(trim($email));

        if (! in_array($role, Access::ROLES, true)) {
            throw ValidationException::withMessages(['role' => __('users.invite.invalid_role')]);
        }
        ManageUser::ensureMayManage($inviter, null, $role);
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => __('users.invite.already_user')]);
        }

        // Un solo invito aperto per indirizzo: quelli precedenti vengono annullati
        Invitation::query()->pending()->where('email', $email)->update(['revoked_at' => now()]);

        $token = Str::random(48);
        $invitation = Invitation::query()->create([
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'token_hash' => Invitation::hashToken($token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(Invitation::VALID_DAYS),
        ]);

        $invitation->notify(new InvitationNotification($token, $inviter->name));
        Audit::log('users.invited', $invitation, $email.' · '.$role, actor: $inviter);

        return $invitation;
    }
}
