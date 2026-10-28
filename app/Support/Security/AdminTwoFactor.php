<?php

namespace App\Support\Security;

use App\Models\User;
use App\Support\Access\Access;

/**
 * Verifica in due passaggi obbligatoria per gli amministratori (impostazione dell'azienda).
 * Con l'obbligo attivo, un amministratore che non l'ha ancora attivata può solo andare nel
 * proprio profilo ad attivarla (o uscire).
 */
final class AdminTwoFactor
{
    public const SETTING = 'security.admin_two_factor_required';

    public static function required(): bool
    {
        return (bool) settings(self::SETTING, false);
    }

    /** La persona deve attivarla adesso prima di continuare */
    public static function missingFor(?User $user): bool
    {
        return $user !== null && self::required() && $user->hasRole(Access::ADMIN) && ! $user->hasTwoFactor();
    }

    /**
     * Amministratori attivi senza verifica in due passaggi (per l'avviso nelle impostazioni).
     *
     * @return list<string>
     */
    public static function adminsWithout(): array
    {
        return User::role(Access::ADMIN)->where('is_active', true)->whereNull('two_factor_confirmed_at')->orderBy('name')->pluck('name')->all();
    }
}
