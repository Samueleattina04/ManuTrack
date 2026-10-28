<?php

use App\Http\Middleware\ApplyCompanyPreferences;
use App\Http\Middleware\EndSessionsAfterPasswordChange;
use App\Http\Middleware\EnsureAppInstalled;
use App\Http\Middleware\EnsureInstallerAvailable;
use App\Http\Middleware\EnsureNotErased;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePassword;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

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
            // Anche per le azioni Livewire (vedi il middleware)
            'password.confirm' => RequirePassword::class,
        ]);

        // CSP e intestazioni di sicurezza su ogni risposta, anche quelle d'errore
        $middleware->append(SecurityHeaders::class);
        // Dietro il proxy HTTPS dell'hosting (DirectAdmin con Nginx davanti ad Apache): solo i
        // proxy locali o quelli in MANUTRACK_TRUSTED_PROXIES, non chiunque (vedi il middleware)
        $middleware->replace(BaseTrustProxies::class, TrustProxies::class);
        // Cambiando o reimpostando la password si chiudono le altre sessioni
        $middleware->web(prepend: [EnsureNotErased::class], append: [EndSessionsAfterPasswordChange::class, ApplyCompanyPreferences::class]);
        // Il rinnovo push arriva dal service worker, che non ha il token CSRF (verifica interna)
        $middleware->validateCsrfTokens(except: ['push/rinnova', 'segnalazioni/invia']);
        // Prima dell'installazione si va all'installazione, non alla pagina di accesso
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: EnsureAppInstalled::class);

        // Chi non ha fatto l'accesso va alla pagina di login; chi l'ha fatto non vede le pagine per ospiti
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Snapshot Livewire modificato a mano: si rifiuta come una pagina scaduta (419), senza
        // scrivere ogni volta l'errore nei registri del server (li riempirebbe a richiesta)
        $exceptions->dontReport(CorruptComponentPayloadException::class);
        $exceptions->render(fn (CorruptComponentPayloadException $e) => response()->view('errors.419', [], 419));
    })->create();
