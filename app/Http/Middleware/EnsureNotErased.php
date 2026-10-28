<?php

namespace App\Http\Middleware;

use App\Support\Gdpr\Eraser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dopo la cancellazione totale l'app non ha più database: ogni pagina mostra solo il
 * rapporto di cosa è stato eliminato (prima di sessioni e cookie, che non esistono più).
 */
class EnsureNotErased
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Eraser::erased()) {
            $report = json_decode((string) file_get_contents(Eraser::reportPath()), true) ?: [];

            return response()->view('gdpr.erased', ['report' => $report], 410)->header('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}
