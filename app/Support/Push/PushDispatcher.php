<?php

namespace App\Support\Push;

use App\Jobs\SendPushMessage;
use App\Models\User;

/**
 * Punto unico per mandare notifiche push alle persone.
 *
 * Le urgenti partono dopo la risposta HTTP (la pagina di chi ha fatto l'azione
 * non aspetta l'invio); le normali vanno in coda.
 */
class PushDispatcher
{
    public function __construct(private PushSender $sender) {}

    /**
     * @param  User|iterable<User|int>  $users
     */
    public function send(User|iterable $users, PushMessage $message, PushUrgency $urgency = PushUrgency::Normal): void
    {
        $ids = [];
        foreach ($users instanceof User ? [$users] : $users as $user) {
            $ids[] = $user instanceof User ? (int) $user->getKey() : (int) $user;
        }
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === [] || ! $this->sender->configured()) {
            return;
        }

        if ($urgency === PushUrgency::Immediate) {
            SendPushMessage::dispatchAfterResponse($ids, $message, $urgency, inline: true);
        } else {
            SendPushMessage::dispatch($ids, $message, $urgency);
        }
    }

    /**
     * Come viene liberata la pagina prima dell'invio urgente su questo server.
     * "fpm" e "litespeed" chiudono la connessione col browser prima dell'invio;
     * con "none" la risposta arriva comunque, ma il browser può restare in attesa
     * finché l'invio non è finito (pochi secondi).
     */
    public static function earlyResponseMode(): string
    {
        return match (true) {
            function_exists('fastcgi_finish_request') => 'fpm',
            function_exists('litespeed_finish_request') => 'litespeed',
            default => 'none',
        };
    }
}
