<?php

namespace App\Http\Controllers;

use App\Support\Brand\Brand;
use Illuminate\Http\JsonResponse;

/**
 * Manifest della PWA, generato con il nome e i colori dell'azienda.
 */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $company = (string) settings('company.name', config('app.name'));

        $manifest = [
            'id' => '/',
            'name' => 'ManuTrack · '.$company,
            'short_name' => 'ManuTrack',
            'description' => __('app.meta_description'),
            'lang' => app()->getLocale(),
            'dir' => 'ltr',
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'any',
            'theme_color' => '#0c0f14',
            'background_color' => '#0c0f14',
            'categories' => ['business', 'productivity', 'utilities'],
            'icons' => [
                ['src' => Brand::iconUrl('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => Brand::iconUrl('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => Brand::iconUrl('maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => Brand::iconUrl('maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
