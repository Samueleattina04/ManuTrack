<?php

namespace App\Http\Controllers;

use App\Support\Brand\Brand;
use App\Support\Brand\CompanyLogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * File del marchio dell'azienda salvati sul disco privato: logo e icone della
 * palette personalizzata. Sono pubblici (servono anche alla pagina di accesso)
 * ma si servono solo questi file, mai percorsi arbitrari.
 */
class BrandingController extends Controller
{
    public function logo(Request $request): Response
    {
        $variant = $request->query('tema') === CompanyLogo::DARK ? CompanyLogo::DARK : CompanyLogo::LIGHT;
        $logo = CompanyLogo::current($variant);
        abort_unless($logo && Storage::disk(CompanyLogo::disk())->exists($logo['path']), 404);

        return Storage::disk(CompanyLogo::disk())->response($logo['path'], 'logo'.'.'.pathinfo($logo['path'], PATHINFO_EXTENSION), [
            'Content-Type' => $logo['mime'],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ], 'inline');
    }

    public function icon(string $version, string $file): Response
    {
        abort_unless(preg_match('/^[a-f0-9]{10}$/', $version) && in_array($file, Brand::ICON_FILES, true), 404);
        $path = "branding/icons/{$version}/{$file}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $file, [
            'Content-Type' => str_ends_with($file, '.svg') ? 'image/svg+xml' : 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
