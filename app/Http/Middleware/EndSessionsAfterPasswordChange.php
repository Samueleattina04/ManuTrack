<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quando la password di una persona cambia (dal profilo, con il link per la nuova password o
 * con l'anonimizzazione) le sue altre sessioni si chiudono alla richiesta successiva: chi
 * aveva rubato o dimenticato aperta una sessione non resta dentro.
 *
 * Come AuthenticateSession di Laravel, ma l'impronta è legata all'id della persona: una
 * sessione in cui entra un'altra persona riparte da capo invece di essere chiusa.
 */
class EndSessionsAfterPasswordChange
{
    private const KEY = 'auth.password_fingerprint';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->user()) {
            return $next($request);
        }

        $stored = $request->session()->get(self::KEY);
        $user = $request->user();
        if (is_array($stored) && ($stored['id'] ?? null) === $user->getAuthIdentifier() && ! hash_equals((string) ($stored['hash'] ?? ''), self::fingerprint($user->getAuthPassword()))) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson() ? response()->json(['message' => __('auth.password_changed_elsewhere')], 401)
                : redirect()->route('login')->withErrors(['email' => __('auth.password_changed_elsewhere')]);
        }

        $response = $next($request);

        // Dopo la richiesta (chi è uscito non c'è più): se la password è cambiata proprio qui,
        // questa sessione resta valida
        $current = Auth::guard('web')->user();
        if ($current !== null) {
            $request->session()->put(self::KEY, ['id' => $current->getAuthIdentifier(), 'hash' => self::fingerprint($current->getAuthPassword())]);
        }

        return $response;
    }

    /** Non si salva l'hash della password nella sessione, solo un'impronta legata alla chiave dell'app */
    private static function fingerprint(string $passwordHash): string
    {
        return hash_hmac('sha256', $passwordHash, (string) config('app.key'));
    }
}
