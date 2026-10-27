<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Access\Access;
use App\Support\Audit\Audit;
use App\Support\Push\PushTransport;
use App\Support\Push\WebPushTransport;
use App\Support\Settings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->bind(PushTransport::class, WebPushTransport::class);
    }

    public function boot(): void
    {
        // Regola unica per tutte le password
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // L'amministratore può tutto
        Gate::before(fn (User $user) => $user->hasRole(Access::ADMIN) ? true : null);

        RateLimiter::for('install', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('invitation', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
        // Segnalazioni: abbastanza per svuotare una coda offline, non per inondare
        RateLimiter::for('reports', fn (Request $request) => Limit::perMinute(30)->by('u'.$request->user()?->id));
        RateLimiter::for('push-test', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));
        RateLimiter::for('push', fn (Request $request) => Limit::perMinute(30)->by($request->user() ? 'u'.$request->user()->id : (string) $request->ip()));

        $this->registerAuditListeners();
    }

    private function registerAuditListeners(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
                Audit::log('auth.login', $event->user, actor: $event->user);
            }
        });
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                Audit::log('auth.logout', $event->user, actor: $event->user);
            }
        });
        Event::listen(Failed::class, function (Failed $event): void {
            Audit::log('auth.failed', $event->user instanceof User ? $event->user : null, description: (string) ($event->credentials['email'] ?? ''));
        });
        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            if ($event->user instanceof User) {
                Audit::log('auth.password_reset', $event->user, actor: $event->user);
            }
        });
        Event::listen(TwoFactorAuthenticationConfirmed::class, fn ($e) => Audit::log('auth.2fa_enabled', $e->user));
        Event::listen(TwoFactorAuthenticationDisabled::class, fn ($e) => Audit::log('auth.2fa_disabled', $e->user));
    }
}
