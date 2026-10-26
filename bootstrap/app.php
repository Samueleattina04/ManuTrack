<?php

use App\Http\Middleware\EnsureAppInstalled;
use App\Http\Middleware\EnsureInstallerAvailable;
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
        ]);

        // Dietro il proxy HTTPS dell'hosting (DirectAdmin con Nginx davanti ad Apache)
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
