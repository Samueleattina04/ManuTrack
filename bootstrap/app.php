<?php

use App\Http\Middleware\ApplyCompanyPreferences;
use App\Http\Middleware\EnsureAppInstalled;
use App\Http\Middleware\EnsureInstallerAvailable;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'installed' => EnsureAppInstalled::class,
            'installer' => EnsureInstallerAvailable::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // Dietro il proxy HTTPS dell'hosting (DirectAdmin con Nginx davanti ad Apache)
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [ApplyCompanyPreferences::class]);
        // Il rinnovo push arriva dal service worker, che non ha il token CSRF (verifica interna)
        $middleware->validateCsrfTokens(except: ['push/rinnova', 'segnalazioni/invia']);
        // Prima dell'installazione si va all'installazione, non alla pagina di accesso
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: EnsureAppInstalled::class);

        // Chi non ha fatto l'accesso va alla pagina di login; chi l'ha fatto non vede le pagine per ospiti
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
