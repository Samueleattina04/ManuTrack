<?php

namespace App\Actions\Users;

use App\Models\Invitation;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    public function handle(Invitation $invitation, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password) {
            $invitation = Invitation::query()->lockForUpdate()->findOrFail($invitation->id);

            if (! $invitation->isPending()) {
                throw ValidationException::withMessages(['password' => __('users.invite.no_longer_valid')]);
            }
            if (User::query()->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['password' => __('users.invite.already_user')]);
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => Hash::make($password),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$invitation->role]);

            $invitation->forceFill(['accepted_at' => now(), 'accepted_user_id' => $user->id])->save();
            Audit::log('users.invitation_accepted', $user, $invitation->role, actor: $user);

            return $user;
        });
    }
}
