<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\Brand\Brand;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Service worker e pagina offline.
 *
 * Il service worker è generato dal server a partire dal manifest di Vite: contiene
 * l'elenco dei file da tenere in cache e una versione che cambia a ogni pacchetto,
 * così il browser si accorge da solo degli aggiornamenti.
 */
class ServiceWorkerController extends Controller
{
    public function worker(): Response
    {
        $assets = $this->buildAssets();
        $precache = array_values(array_unique([
            route('pwa.offline', absolute: false),
            ...$assets,
            parse_url(Brand::iconUrl('icon-192.png'), PHP_URL_PATH),
            parse_url(Brand::iconUrl('favicon.svg'), PHP_URL_PATH),
        ]));

        $version = manutrack_version().'-'.substr(sha1(implode('|', $precache)), 0, 10);

        return response()
            ->view('pwa.sw', ['version' => $version, 'precache' => $precache], 200)
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Service-Worker-Allowed', '/');
    }

    public function offline(): View
    {
        return view('pwa.offline');
    }

    /**
     * File compilati da Vite da tenere in cache: CSS, JS e solo i caratteri latini.
     *
     * @return list<string>
     */
    private function buildAssets(): array
    {
        $file = public_path('build/manifest.json');
        if (! is_file($file)) {
            return [];
        }

        $manifest = (array) json_decode((string) file_get_contents($file), true);
        $out = [];
        foreach ($manifest as $entry) {
            if (! is_array($entry) || ! isset($entry['file'])) {
                continue;
            }
            $path = (string) $entry['file'];
            // Librerie caricate solo quando servono e inutili senza rete (i grafici vogliono i dati
            // dal server): non si scaricano all'installazione dell'app
            if (($entry['isDynamicEntry'] ?? false) && str_starts_with(basename($path), 'chart-')) {
                continue;
            }
            $isFont = str_ends_with($path, '.woff2');
            if ($isFont && ! (str_contains($path, '-latin-') && ! str_contains($path, '-latin-ext-'))) {
                continue;
            }
            if (! $isFont && ! str_ends_with($path, '.js') && ! str_ends_with($path, '.css')) {
                continue;
            }
            $out[] = '/build/'.$path;
            foreach ((array) ($entry['css'] ?? []) as $css) {
                $out[] = '/build/'.$css;
            }
        }
        sort($out);

        return array_values(array_unique($out));
    }
}
