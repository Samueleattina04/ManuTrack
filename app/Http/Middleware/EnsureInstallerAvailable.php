<?php

namespace App\Http\Middleware;

use App\Support\Setup\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'installazione dal browser esiste solo finché l'app non è installata:
 * dopo, le sue pagine rispondono 404.
 */
class EnsureInstallerAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(InstallState::isInstalled(), 404);

        return $next($request);
    }
}
