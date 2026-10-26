<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applica lingua e fuso orario scelti nelle impostazioni dell'azienda.
 */
class ApplyCompanyPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) settings('company.locale', config('app.locale'));
        if (is_dir(lang_path($locale))) {
            app()->setLocale($locale);
            Carbon::setLocale($locale);
        }

        $timezone = (string) settings('company.timezone', config('app.timezone'));
        if (in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        return $next($request);
    }
}
