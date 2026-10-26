<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        // Pochi tentativi per l'installazione dal browser
        RateLimiter::for('install', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
    }
}
