<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Limite di tentativi per le azioni Livewire: passano tutte da /livewire/update, che non ha
 * un limite per azione (l'invio di email, le esportazioni e i controlli della password sì).
 */
final class ActionLimit
{
    /** Conta un tentativo; false se il limite è già raggiunto (il tentativo non si esegue) */
    public static function attempt(string $key, int $max, int $decaySeconds): bool
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return false;
        }
        RateLimiter::hit($key, $decaySeconds);

        return true;
    }

    /** Messaggio con l'attesa, da mostrare quando attempt() restituisce false */
    public static function message(string $key): string
    {
        return __('app.too_many_attempts', ['seconds' => max(1, RateLimiter::availableIn($key))]);
    }

    /** Chiave per persona collegata */
    public static function forUser(string $action): string
    {
        return $action.':u'.auth()->id();
    }
}
