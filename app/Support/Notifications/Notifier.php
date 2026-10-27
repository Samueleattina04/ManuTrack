<?php

namespace App\Support\Notifications;

use App\Enums\NotificationEvent;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\EventMail;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessage;
use App\Support\Push\PushUrgency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Punto unico degli avvisi. Per ogni persona:
 * - salva l'avviso nel centro notifiche (sempre);
 * - manda la push se la persona la vuole (urgenti subito dopo la risposta, le altre in coda);
 * - manda l'email subito, oppure la mette nel riepilogo giornaliero, oppure niente.
 */
class Notifier
{
    public function __construct(
        private PushDispatcher $push,
        private NotificationSettings $settings,
    ) {}

    /**
     * @param  User|iterable<User|int>  $users
     */
    public function send(NotificationEvent $event, User|iterable $users, PushMessage $message, PushUrgency $urgency = PushUrgency::Normal): void
    {
        $users = $this->resolve($users);
        if ($users->isEmpty()) {
            return;
        }

        $channels = $this->settings->channels($users, $event);
        $pushIds = [];
        $mailNow = [];
        $urgent = $urgency === PushUrgency::Immediate;

        foreach ($users as $user) {
            $c = $channels[$user->id];
            try {
                UserNotification::query()->create([
                    'user_id' => $user->id,
                    'event' => $event,
                    'title' => mb_strimwidth($message->title, 0, 160, '…'),
                    'body' => $message->body !== '' ? mb_strimwidth($message->body, 0, 500, '…') : null,
                    'url' => $message->url,
                    'tag' => $message->tag,
                    'urgent' => $urgent,
                    'digest' => $c['email'] === 'riepilogo',
                ]);
            } catch (Throwable $e) {
                report($e); // l'avviso nel centro non deve bloccare la push
            }
            if ($c['push']) {
                $pushIds[] = $user->id;
            }
            if ($c['email'] === 'subito' && $user->email) {
                $mailNow[] = $user;
            }
        }

        $this->push->send($pushIds, $message, $urgency);

        if ($mailNow !== []) {
            try {
                Notification::send($mailNow, new EventMail($event, $message, $urgent));
            } catch (Throwable $e) {
                report($e); // un problema di posta non deve far fallire l'azione
            }
        }
    }

    /**
     * @param  User|iterable<User|int>  $users
     * @return Collection<int, User>
     */
    private function resolve(User|iterable $users): Collection
    {
        $ids = [];
        foreach ($users instanceof User ? [$users] : $users as $user) {
            $ids[] = $user instanceof User ? (int) $user->getKey() : (int) $user;
        }
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? collect() : User::query()->whereIn('id', $ids)->where('is_active', true)->with('roles')->get();
    }
}
