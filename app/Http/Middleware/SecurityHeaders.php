<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Intestazioni di sicurezza su tutte le risposte (docs/SICUREZZA.md).
 *
 * Le pagine HTML hanno una Content-Security-Policy con un nonce nuovo a ogni richiesta per
 * gli script e gli stili in linea (tema, palette, configurazione di Livewire). Eccezioni:
 * - 'unsafe-eval' negli script: Livewire 3 contiene Alpine nella build standard, che valuta
 *   le espressioni dei template (wire:click, x-data…) con new Function;
 * - style-src-attr 'unsafe-inline': attributi style="" nei template e quelli che Livewire e
 *   Alpine impostano durante gli aggiornamenti (non eseguono codice);
 * - img-src e connect-src data: blob:: anteprime delle foto prima del caricamento, immagini
 *   dentro i modelli GLB (Three.js le legge con fetch() da blob: o data:), icone in data: del
 *   CSS. Nessuno dei due esce dal browser: verso la rete resta solo 'self'.
 * Le risposte che hanno già una loro CSP (allegati, modelli 3D, logo) la tengono.
 */
class SecurityHeaders
{
    /** Pagine che usano la fotocamera (lettura del QR nella segnalazione rapida) */
    private const CAMERA_ROUTES = ['requests.create'];

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'same-origin', false);
        $headers->set('X-Frame-Options', 'DENY', false);
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin', false);
        $camera = in_array($request->route()?->getName(), self::CAMERA_ROUTES, true) ? 'camera=(self)' : 'camera=()';
        $headers->set('Permissions-Policy', $camera.', microphone=(), geolocation=(), payment=(), usb=(), serial=(), bluetooth=(), midi=(), display-capture=()', false);

        $hsts = (int) config('manutrack.security.hsts_seconds');
        if ($hsts > 0 && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age='.$hsts, false);
        }

        $mode = (string) config('manutrack.security.csp');
        $isHtml = str_contains((string) $headers->get('Content-Type', 'text/html'), 'text/html');
        if ($mode !== 'off' && $isHtml && ! $headers->has('Content-Security-Policy')) {
            $headers->set($mode === 'report' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', self::policy($nonce));
        }

        return $response;
    }

    public static function policy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
            "style-src 'self' 'nonce-{$nonce}'",
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self' data: blob:",
            "media-src 'self' blob:",
            "worker-src 'self'",
            "manifest-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
