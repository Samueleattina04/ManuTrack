<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;

/**
 * Proxy fidati: di serie solo questo server e le reti private, non chiunque ("*").
 *
 * Con "*" un client che arriva direttamente ad Apache può scrivere X-Forwarded-For e
 * X-Forwarded-Host a piacere: cambia indirizzo a ogni tentativo (e aggira i limiti per IP)
 * e falsa l'IP nel registro attività. MANUTRACK_TRUSTED_PROXIES cambia l'elenco.
 */
class TrustProxies extends BaseTrustProxies
{
    /** Indirizzi da cui arriva un proxy sulla stessa macchina o nella rete interna */
    public const LOCAL = ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7'];

    /**
     * @return array<int, string>|string
     */
    protected function proxies()
    {
        $configured = trim((string) config('manutrack.trusted_proxies'));
        if ($configured !== '') {
            return $configured;
        }

        // Alcuni pannelli fanno passare Nginx verso Apache dall'indirizzo pubblico del server stesso
        $proxies = self::LOCAL;
        $server = request()->server('SERVER_ADDR');
        if (is_string($server) && $server !== '' && ! in_array($server, $proxies, true)) {
            $proxies[] = $server;
        }

        return $proxies;
    }
}
