<?php

namespace App\Http\Middleware;

use App\Support\Setup\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prima dell'installazione ogni pagina porta all'installazione guidata.
 */
class EnsureAppInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! InstallState::isInstalled()) {
            return redirect()->route('install.show');
        }

        return $next($request);
    }
}
