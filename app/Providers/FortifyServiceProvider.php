<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;

/**
 * Accesso con Fortify: schermate nostre (Blade), niente registrazione libera.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LogoutResponse::class, \App\Http\Responses\LogoutResponse::class);
    }

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        // Gli utenti disattivati non entrano, con un messaggio chiaro
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('email', mb_strtolower(trim((string) $request->input('email'))))->first();

            if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if (! $user->is_active) {
                throw ValidationException::withMessages(['email' => __('auth.inactive')]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $key = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return [Limit::perMinute(5)->by($key), Limit::perMinute(30)->by((string) $request->ip())];
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by((string) $request->session()->get('login.id')));
    }
}
