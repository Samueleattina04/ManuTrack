<?php

namespace App\Support\Setup;

use Illuminate\Http\Request;

/**
 * Controllo di APP_URL. Da APP_URL nascono tutti gli indirizzi che l'app manda fuori (link per
 * la nuova password, inviti, email, notifiche, QR): se è sbagliato i link portano altrove o non
 * funzionano. Deve essere https (in produzione) e avere lo stesso dominio con cui si apre l'app.
 */
final class AppUrlCheck
{
    /**
     * Problemi trovati: chiave => messaggio. Vuoto = tutto bene.
     * $request null (terminale): non si può confrontare il dominio.
     *
     * @return array<string, string>
     */
    public static function problems(?Request $request = null): array
    {
        $url = trim((string) config('app.url'));
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($url === '' || $host === '' || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return ['missing' => __('install.app_url.missing', ['url' => $url === '' ? '—' : $url])];
        }

        $problems = [];
        if (app()->isProduction() && $parts['scheme'] !== 'https') {
            $problems['https'] = __('install.app_url.https', ['url' => $url]);
        }
        $local = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.local');
        if (app()->isProduction() && $local) {
            $problems['local'] = __('install.app_url.local', ['url' => $url]);
        }
        if ($request !== null) {
            $actual = strtolower($request->getHost());
            if ($actual !== '' && $actual !== $host) {
                $problems['host'] = __('install.app_url.host', ['url' => $url, 'host' => $actual]);
            }
            if (app()->isProduction() && $parts['scheme'] === 'https' && ! $request->isSecure()) {
                $problems['request_https'] = __('install.app_url.request_https');
            }
        }

        return $problems;
    }

    /**
     * Riga per l'elenco dei requisiti (installazione e pagina Sistema).
     *
     * @return array{key: string, label: string, status: string, detail: string}
     */
    public static function requirement(?Request $request = null): array
    {
        $problems = self::problems($request);

        return [
            'key' => 'app-url',
            'label' => __('install.app_url.label'),
            'status' => $problems === [] ? Requirements::OK : Requirements::ERROR,
            'detail' => $problems === [] ? (string) config('app.url') : implode(' ', $problems),
        ];
    }
}
