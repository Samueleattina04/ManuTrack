<?php

namespace App\Http\Middleware;

use App\Support\Security\AdminTwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Con l'obbligo attivo, un amministratore senza verifica in due passaggi viene portato al
 * profilo (l'unica pagina che resta aperta, insieme all'uscita) finché non la attiva.
 * È "persistente" per Livewire: vale anche per le azioni delle pagine già aperte.
 */
class RequireAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! AdminTwoFactor::missingFor($request->user()) || $request->routeIs('profile', 'logout')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
            abort(403, __('settings.two_factor.blocked'));
        }

        return redirect()->route('profile')->with('two_factor_required', true);
    }
}
