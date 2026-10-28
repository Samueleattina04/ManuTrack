<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limiti per i moduli di Fortify che non ne hanno uno proprio (Fortify limita solo accesso
 * e verifica in due passaggi): richiesta del link per la nuova password, uso del link e
 * conferma della password. I limiti sono in AppServiceProvider.
 */
class ThrottleAuthForms
{
    /** Nome della rotta => limite */
    private const LIMITERS = [
        'password.email' => 'password-email',
        'password.update' => 'password-reset',
        'password.confirm.store' => 'password-confirm',
    ];

    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        $limiter = self::LIMITERS[(string) $request->route()?->getName()] ?? null;
        if ($limiter === null || ! $request->isMethod('POST')) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
