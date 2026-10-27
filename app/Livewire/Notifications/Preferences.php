<?php

namespace App\Livewire\Notifications;

use App\Enums\NotificationEvent;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Preferenze personali: per ogni evento che la persona può ricevere,
 * push sì/no ed email (no, subito, riepilogo giornaliero).
 */
class Preferences extends Component
{
    /** @var array<string, array{push?: bool, email?: string}> dal modulo: le chiavi possono mancare */
    public array $prefs = [];

    public function mount(NotificationSettings $settings): void
    {
        $user = auth()->user();
        $events = NotificationSettings::eventsFor($user);
        $saved = NotificationPreference::query()->where('user_id', $user->id)->get()->keyBy('event');
        foreach ($events as $event) {
            $p = $saved->get($event->value);
            $this->prefs[$event->value] = [
                'push' => $p->push ?? $event->defaultPush(),
                'email' => $p->email ?? $settings->defaultEmailFor($user, $event),
            ];
        }
    }

    public function save(NotificationSettings $settings): void
    {
        $user = auth()->user();
        $allowed = collect(NotificationSettings::eventsFor($user))->keyBy(fn (NotificationEvent $e) => $e->value);
        foreach ($this->prefs as $event => $choice) {
            $e = $allowed->get($event);
            if (! $e || ! in_array($choice['email'] ?? '', NotificationSettings::EMAIL_MODES, true)) {
                continue;
            }
            $push = (bool) ($choice['push'] ?? false);
            // Solo le differenze dal predefinito restano salvate: se il ruolo cambia, valgono le nuove regole
            $pushValue = $push === $e->defaultPush() ? null : $push;
            $emailValue = $choice['email'] === $settings->defaultEmailFor($user, $e) ? null : $choice['email'];
            if ($pushValue === null && $emailValue === null) {
                NotificationPreference::query()->where('user_id', $user->id)->where('event', $event)->delete();
            } else {
                NotificationPreference::query()->updateOrCreate(['user_id' => $user->id, 'event' => $event], ['push' => $pushValue, 'email' => $emailValue]);
            }
        }
        $this->dispatch('toast', message: __('notifications.preferences.saved'));
    }

    public function render(): View
    {
        return view('livewire.notifications.preferences', [
            'events' => NotificationSettings::eventsFor(auth()->user()),
            'hasDevice' => PushSubscription::query()->where('user_id', auth()->id())->exists(),
            'modes' => NotificationSettings::EMAIL_MODES,
        ])->title(__('notifications.preferences.title'))->layoutData(['heading' => __('notifications.preferences.title')]);
    }
}
