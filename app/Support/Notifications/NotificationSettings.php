<?php

namespace App\Support\Notifications;

use App\Enums\NotificationEvent;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Canali di ogni persona per ogni evento:
 * - push: scelta della persona, altrimenti il predefinito dell'evento;
 * - email: scelta della persona, altrimenti la regola del suo ruolo decisa
 *   dall'amministratore (con più ruoli vale la più forte), altrimenti il
 *   predefinito dell'evento.
 * L'avviso nel centro notifiche c'è sempre.
 */
class NotificationSettings
{
    public const EMAIL_MODES = ['no', 'riepilogo', 'subito'];

    public const RULES_SETTING = 'notifications.email_rules';

    public function __construct(private Settings $settings) {}

    /**
     * Regole email per ruolo: [ruolo => [evento => modo]]
     *
     * @return array<string, array<string, string>>
     */
    public function roleRules(): array
    {
        $rules = $this->settings->get(self::RULES_SETTING, []);

        return is_array($rules) ? $rules : [];
    }

    /**
     * @param  array<string, mixed>  $rules  dal modulo: si tengono solo ruoli, eventi e modi validi
     */
    public function saveRoleRules(array $rules): void
    {
        $clean = [];
        foreach ($rules as $role => $events) {
            if (! in_array($role, Access::ROLES, true) || ! is_array($events)) {
                continue;
            }
            foreach ($events as $event => $mode) {
                if (NotificationEvent::tryFrom((string) $event) && in_array($mode, self::EMAIL_MODES, true) && $mode !== NotificationEvent::from((string) $event)->defaultEmail()) {
                    $clean[$role][$event] = $mode;
                }
            }
        }
        $this->settings->set(self::RULES_SETTING, $clean);
    }

    /** Email per un ruolo e un evento, senza scelte personali */
    public function roleEmail(string $role, NotificationEvent $event): string
    {
        return $this->roleRules()[$role][$event->value] ?? $event->defaultEmail();
    }

    /**
     * Canali effettivi per più persone in una volta.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, array{push: bool, email: string}>
     */
    public function channels(Collection $users, NotificationEvent $event): array
    {
        $prefs = NotificationPreference::query()->whereIn('user_id', $users->map(fn (User $u) => $u->id)->all())->where('event', $event->value)->get()->keyBy('user_id');
        $out = [];
        foreach ($users as $user) {
            /** @var NotificationPreference|null $pref */
            $pref = $prefs->get($user->id);
            $out[$user->id] = [
                'push' => $pref->push ?? $event->defaultPush(),
                'email' => $pref->email ?? $this->defaultEmailFor($user, $event),
            ];
        }

        return $out;
    }

    /** Email predefinita per la persona: la regola più forte fra i suoi ruoli */
    public function defaultEmailFor(User $user, NotificationEvent $event): string
    {
        $modes = $user->getRoleNames()->map(fn (string $role) => $this->roleEmail($role, $event));
        if ($modes->isEmpty()) {
            return $event->defaultEmail();
        }

        return $modes->sortByDesc(fn (string $m) => array_search($m, self::EMAIL_MODES, true))->first();
    }

    /**
     * Eventi che una persona può ricevere (per la pagina delle preferenze).
     *
     * @return list<NotificationEvent>
     */
    public static function eventsFor(User $user): array
    {
        return array_values(array_filter(NotificationEvent::cases(), fn (NotificationEvent $e) => $user->can($e->permission())));
    }
}
