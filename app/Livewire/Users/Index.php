<?php

namespace App\Livewire\Users;

use App\Actions\Users\InviteUser;
use App\Actions\Users\ManageUser;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\Audit\Audit;
use App\Support\Security\ActionLimit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Persone dell'azienda: elenco, inviti, ruoli, attivazione.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $status = 'active';

    public bool $inviting = false;

    public string $inviteEmail = '';

    public string $inviteName = '';

    public string $inviteRole = Access::TECHNICIAN;

    public function paginationView(): string
    {
        return 'components.pagination';
    }

    public function mount(): void
    {
        $this->authorize('users.view');
    }

    public function updating(string $field): void
    {
        if (in_array($field, ['search', 'role', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function invite(InviteUser $invite): void
    {
        $this->authorize('users.manage');
        $this->validate([
            'inviteEmail' => ['required', 'email', 'max:190'],
            'inviteName' => ['nullable', 'string', 'max:120'],
            'inviteRole' => ['required', 'in:'.implode(',', Access::ROLES)],
        ], attributes: ['inviteEmail' => __('users.field.email'), 'inviteName' => __('users.field.name'), 'inviteRole' => __('users.field.role')]);
        if ($message = $this->inviteLimit($this->inviteEmail)) {
            $this->addError('inviteEmail', $message);

            return;
        }

        try {
            $invite->handle(auth()->user(), $this->inviteEmail, $this->inviteRole, $this->inviteName ?: null);
        } catch (ValidationException $e) {
            $this->addError('inviteEmail', collect($e->errors())->flatten()->first());

            return;
        }

        $this->dispatch('toast', message: __('users.invite.sent', ['email' => $this->inviteEmail]));
        $this->reset('inviteEmail', 'inviteName', 'inviting');
    }

    public function resend(int $invitationId, InviteUser $invite): void
    {
        $this->authorize('users.manage');
        $invitation = Invitation::query()->findOrFail($invitationId);
        if ($message = $this->inviteLimit($invitation->email)) {
            $this->dispatch('toast', message: $message, tone: 'ko');

            return;
        }
        $this->guarded(function () use ($invite, $invitation) {
            $invite->handle(auth()->user(), $invitation->email, $invitation->role, $invitation->name);
            $this->dispatch('toast', message: __('users.invite.sent', ['email' => $invitation->email]));
        });
    }

    public function revoke(int $invitationId): void
    {
        $this->authorize('users.manage');
        $invitation = Invitation::query()->findOrFail($invitationId);
        $invitation->forceFill(['revoked_at' => now()])->save();
        Audit::log('users.invitation_revoked', $invitation, $invitation->email);
    }

    public function changeRole(int $userId, string $role, ManageUser $manage): void
    {
        $this->authorize('users.manage');
        $this->guarded(fn () => $manage->changeRole(auth()->user(), User::query()->whereNull('anonymized_at')->findOrFail($userId), $role));
    }

    public function toggleActive(int $userId, ManageUser $manage): void
    {
        $this->authorize('users.manage');
        $user = User::query()->findOrFail($userId);
        $this->guarded(fn () => $manage->setActive(auth()->user(), $user, ! $user->is_active));
    }

    public function resetTwoFactor(int $userId, ManageUser $manage): void
    {
        $this->authorize('users.manage');
        $this->guarded(function () use ($manage, $userId) {
            $manage->resetTwoFactor(auth()->user(), User::query()->findOrFail($userId));
            $this->dispatch('toast', message: __('users.two_factor_reset_done'));
        });
    }

    public function sendPasswordReset(int $userId): void
    {
        $this->authorize('users.manage');
        $user = User::query()->whereNull('anonymized_at')->findOrFail($userId);
        if (! auth()->user()->isAdmin() && $user->isAdmin()) {
            $this->dispatch('toast', message: __('users.admin_only'), tone: 'ko');

            return;
        }
        $key = ActionLimit::forUser('reset-link');
        if (! ActionLimit::attempt($key, 10, 600)) {
            $this->dispatch('toast', message: ActionLimit::message($key), tone: 'ko');

            return;
        }
        // Il link si invia al massimo una volta al minuto per persona (config/auth.php)
        if (Password::sendResetLink(['email' => $user->email]) !== Password::RESET_LINK_SENT) {
            $this->dispatch('toast', message: __('passwords.throttled'), tone: 'ko');

            return;
        }
        Audit::log('users.password_link_sent', $user, $user->email);
        $this->dispatch('toast', message: __('users.reset_link_sent', ['email' => $user->email]));
    }

    /**
     * Inviti per email: al massimo 20 l'ora per chi invita e 3 l'ora per lo stesso indirizzo
     * (niente raffiche di email né quota della posta esaurita). Messaggio se superato.
     */
    private function inviteLimit(string $email): ?string
    {
        foreach ([[ActionLimit::forUser('invite'), 20], ['invite-email:'.sha1(mb_strtolower(trim($email))), 3]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return ActionLimit::message($key);
            }
        }
        RateLimiter::hit(ActionLimit::forUser('invite'), 3600);
        RateLimiter::hit('invite-email:'.sha1(mb_strtolower(trim($email))), 3600);

        return null;
    }

    private function guarded(callable $action): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: (string) collect($e->errors())->flatten()->first(), tone: 'ko');
        }
    }

    public function render(): View
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')
                ->orWhere('job_title', 'like', '%'.$this->search.'%')))
            ->when($this->role !== '', fn ($q) => $q->role($this->role))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.users.index', [
            'users' => $users,
            'invitations' => Invitation::query()->pending()->with('inviter')->latest()->get(),
            'roles' => Access::ROLES,
            'canManage' => auth()->user()->can('users.manage'),
            'isAdmin' => auth()->user()->hasRole(Access::ADMIN),
        ])->title(__('users.title'))->layoutData(['heading' => __('users.title')]);
    }
}
