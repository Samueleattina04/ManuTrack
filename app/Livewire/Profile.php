<?php

namespace App\Livewire;

use App\Support\Audit\Audit;
use App\Support\Security\ActionLimit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Component;

/**
 * Profilo personale: dati, password, verifica in due passaggi, tema.
 */
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $jobTitle = '';

    public string $phone = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $twoFactorPassword = '';

    public string $twoFactorCode = '';

    public bool $showRecoveryCodes = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->jobTitle = (string) $user->job_title;
        $this->phone = (string) $user->phone;
    }

    public function saveProfile(): void
    {
        $user = auth()->user();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'jobTitle' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
        ], attributes: ['name' => __('users.field.name'), 'email' => __('users.field.email')]);

        $user->fill(['name' => $data['name'], 'email' => $data['email'], 'job_title' => $data['jobTitle'] ?: null, 'phone' => $data['phone'] ?: null])->save();
        Audit::log('profile.updated', $user, null, Audit::diff($user));
        $this->dispatch('toast', message: __('profile.saved'));
    }

    public function changePassword(): void
    {
        $this->limitPasswordChecks();
        $user = auth()->user();
        $this->validate([
            'currentPassword' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], attributes: ['currentPassword' => __('profile.current_password'), 'password' => __('auth_ui.new_password')]);

        // Nuovo codice "ricordami": sugli altri dispositivi non si rientra con la vecchia password
        $user->forceFill(['password' => Hash::make($this->password), 'remember_token' => Str::random(60)])->save();
        Audit::log('profile.password_changed', $user);
        $this->reset('currentPassword', 'password', 'password_confirmation');
        $this->dispatch('toast', message: __('profile.password_changed'));
    }

    public function enableTwoFactor(EnableTwoFactorAuthentication $enable): void
    {
        $this->limitPasswordChecks();
        $this->validate(['twoFactorPassword' => ['required', 'current_password']], attributes: ['twoFactorPassword' => __('auth_ui.password')]);
        $enable(auth()->user());
        $this->reset('twoFactorPassword');
    }

    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['twoFactorCode' => ['required', 'string']], attributes: ['twoFactorCode' => __('auth_ui.two_factor.code')]);
        $confirm(auth()->user(), preg_replace('/\s+/', '', $this->twoFactorCode));
        $this->reset('twoFactorCode');
        $this->showRecoveryCodes = true;
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $this->limitPasswordChecks();
        $this->validate(['twoFactorPassword' => ['required', 'current_password']], attributes: ['twoFactorPassword' => __('auth_ui.password')]);
        $generate(auth()->user());
        $this->reset('twoFactorPassword');
        $this->showRecoveryCodes = true;
    }

    public function disableTwoFactor(DisableTwoFactorAuthentication $disable): void
    {
        $this->limitPasswordChecks();
        $this->validate(['twoFactorPassword' => ['required', 'current_password']], attributes: ['twoFactorPassword' => __('auth_ui.password')]);
        $disable(auth()->user());
        $this->reset('twoFactorPassword', 'showRecoveryCodes');
    }

    /**
     * La password attuale si verifica al massimo 5 volte al minuto: chi trova una sessione
     * aperta non può provarne a raffica (il limite di accesso qui non vale).
     */
    private function limitPasswordChecks(): void
    {
        $key = ActionLimit::forUser('password-check');
        if (! ActionLimit::attempt($key, 5, 60)) {
            throw ValidationException::withMessages(['currentPassword' => ActionLimit::message($key), 'twoFactorPassword' => ActionLimit::message($key)]);
        }
    }

    public function render(): View
    {
        $user = auth()->user()->fresh();

        return view('livewire.profile', [
            'user' => $user,
            'pendingTwoFactor' => $user->two_factor_secret && ! $user->hasTwoFactor(),
        ])->title(__('profile.title'))->layoutData(['heading' => __('profile.title')]);
    }
}
