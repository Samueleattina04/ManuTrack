<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequireAdminTwoFactor;
use App\Http\Middleware\RequirePassword;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->bind(PushTransport::class, WebPushTransport::class);
    }

    public function boot(): void
    {
        // Gli indirizzi nelle email (nuova password, inviti) e nelle pagine vengono sempre da
        // APP_URL, mai dall'intestazione Host o X-Forwarded-Host della richiesta: altrimenti
        // chiunque potrebbe farsi spedire a un'altra persona un link verso il proprio sito
        $root = rtrim((string) config('app.url'), '/');
        if ($root !== '') {
            URL::forceRootUrl($root);
            if (str_starts_with($root, 'https://')) {
                URL::forceScheme('https');
            }
        }

        // In produzione migrate:fresh, migrate:reset, migrate:refresh e db:wipe non partono,
        // nemmeno con --force: un comando sbagliato non cancella i dati di un cliente
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Regola unica per tutte le password
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // L'amministratore può tutto
        Gate::before(fn (User $user) => $user->hasRole(Access::ADMIN) ? true : null);

        // Costi dei ricambi: chi gestisce il magazzino o chi vede i costi degli ordini
        Gate::define('inventory.view_costs', fn (User $user) => $user->can('inventory.manage') || $user->can('work_orders.view_costs'));

        RateLimiter::for('install', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('invitation', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
        // Segnalazioni: abbastanza per svuotare una coda offline, non per inondare
        RateLimiter::for('reports', fn (Request $request) => Limit::perMinute(30)->by('u'.$request->user()?->id));
        RateLimiter::for('push-test', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));
        RateLimiter::for('push', fn (Request $request) => Limit::perMinute(30)->by($request->user() ? 'u'.$request->user()->id : (string) $request->ip()));
        // Link per la nuova password: per indirizzo IP. Per persona Laravel ne manda già uno al
        // minuto; un limite per email permetterebbe a chiunque di bloccare il reset di un altro
        RateLimiter::for('password-email', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
        // Conferma della password: chi ha una sessione aperta non la prova a raffica
        RateLimiter::for('password-confirm', fn (Request $request) => Limit::perMinute(5)->by('u'.$request->user()?->id.'|'.$request->ip()));
        // Esportazioni CSV/Excel e rapporto mensile: query pesanti
        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(10)->by('u'.$request->user()?->id));
        // Caricamenti (temporanei di Livewire e modelli 3D): per persona, o per IP prima dell'accesso
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(60)->by($request->user() ? 'u'.$request->user()->id : (string) $request->ip()));

        // Le azioni Livewire (/livewire/update) ripassano dai middleware della pagina solo se
        // sono in questo elenco: senza, una persona disattivata con la pagina aperta continua
        // a lavorare e le pagine con la password riconfermata restano usabili dopo la scadenza
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class, RequireAdminTwoFactor::class, RequirePassword::class]);

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
