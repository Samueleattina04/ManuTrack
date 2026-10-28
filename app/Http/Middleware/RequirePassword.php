<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword as BaseRequirePassword;
use Illuminate\Http\Request;

/**
 * Password riconfermata ("password.confirm"), anche per le azioni Livewire.
 *
 * Livewire riapplica questo middleware a ogni chiamata a /livewire/update partita da una
 * pagina protetta, ma blocca l'azione solo se il middleware risponde con un
 * reindirizzamento: la risposta JSON 423 di Laravel (per chi manda Accept: application/json)
 * verrebbe ignorata e l'azione eseguita. Per le richieste Livewire si reindirizza sempre.
 */
class RequirePassword extends BaseRequirePassword
{
    public function handle($request, Closure $next, $redirectToRoute = null, $passwordTimeoutSeconds = null)
    {
        /** @var Request $request */
        if ($request->hasHeader('X-Livewire') && $this->shouldConfirmPassword($request, $passwordTimeoutSeconds)) {
            // Dopo la conferma si torna alla pagina del componente, non a /livewire/update
            $request->session()->put('url.intended', $request->fullUrl());

            return $this->responseFactory->redirectTo($this->urlGenerator->route($redirectToRoute ?: 'password.confirm'));
        }

        return parent::handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);
    }
}
