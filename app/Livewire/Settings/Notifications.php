<?php

namespace App\Livewire\Settings;

use App\Enums\NotificationEvent;
use App\Support\Access\Access;
use App\Support\Audit\Audit;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Email per ruolo: per ogni ruolo e ogni evento, se l'email parte subito, va nel
 * riepilogo giornaliero o non parte. Ognuno può poi cambiare le proprie scelte.
 */
class Notifications extends Component
{
    /** @var array<string, array<string, string>> */
    public array $rules = [];

    public function mount(NotificationSettings $settings): void
    {
        $this->authorize('settings.manage');
        foreach (Access::ROLES as $role) {
            foreach (NotificationEvent::cases() as $event) {
                $this->rules[$role][$event->value] = $settings->roleEmail($role, $event);
            }
        }
    }

    public function save(NotificationSettings $settings): void
    {
        $this->authorize('settings.manage');
        $settings->saveRoleRules($this->rules);
        Audit::log('settings.notifications', null, __('notifications.rules.audit'));
        $this->dispatch('toast', message: __('notifications.rules.saved'));
    }

    public function render(): View
    {
        return view('livewire.settings.notifications', [
            'roles' => Access::ROLES,
            'events' => NotificationEvent::cases(),
            'modes' => NotificationSettings::EMAIL_MODES,
        ])->title(__('notifications.rules.title'))->layoutData(['heading' => __('notifications.rules.title')]);
    }
}
