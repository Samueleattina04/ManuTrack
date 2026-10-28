<?php

namespace App\Http\Controllers;

use App\Actions\Setup\AlreadyInstalledException;
use App\Actions\Setup\InstallApplication;
use App\Support\Setup\AppUrlCheck;
use App\Support\Setup\EnvWriter;
use App\Support\Setup\Requirements;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * Installazione guidata dal browser, per gli hosting senza accesso SSH.
 *
 * Protezioni: risponde 404 se l'app è già installata (middleware "installer"),
 * richiede il codice MANUTRACK_INSTALL_TOKEN da .env, ha un limite di tentativi
 * e al termine cancella il codice da .env e scrive il file di blocco.
 */
class InstallController extends Controller
{
    public function show(Requirements $requirements): View
    {
        return view('install.form', [
            'checks' => $requirements->checks(),
            'passes' => $requirements->passes(),
            'tokenConfigured' => filled(config('manutrack.install_token')),
            'palettes' => config('manutrack.palettes'),
        ]);
    }

    public function store(Request $request, InstallApplication $install): RedirectResponse|View
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'company' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'palette' => ['required', 'in:'.implode(',', array_keys((array) config('manutrack.palettes')))],
            'vapid' => ['sometimes', 'boolean'],
        ]);

        // APP_URL sbagliato = link per la nuova password e inviti che portano altrove: si corregge prima
        $urlProblems = AppUrlCheck::problems($request);
        if ($urlProblems !== []) {
            return back()->withInput($request->except(['password', 'password_confirmation', 'token']))
                ->withErrors(['install' => implode(' ', $urlProblems)]);
        }

        $expected = (string) config('manutrack.install_token');
        if ($expected === '' || ! hash_equals($expected, (string) $data['token'])) {
            return back()->withInput($request->except(['password', 'password_confirmation', 'token']))
                ->withErrors(['token' => __('install.token_invalid')]);
        }

        try {
            $result = $install->handle([
                'company' => $data['company'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'palette' => $data['palette'],
                'vapid' => (bool) ($data['vapid'] ?? false),
            ]);
        } catch (AlreadyInstalledException) {
            abort(404);
        } catch (Throwable $e) {
            Log::error('Installazione non riuscita', ['exception' => $e]);

            return back()->withInput($request->except(['password', 'password_confirmation', 'token']))
                ->withErrors(['install' => __('install.failed', ['message' => $e->getMessage()])]);
        }

        // Il codice non serve più: lo si toglie da .env
        $tokenRemoved = false;
        try {
            $env = new EnvWriter;
            if ($env->isWritable()) {
                $env->set(['MANUTRACK_INSTALL_TOKEN' => '']);
                $tokenRemoved = true;
            }
        } catch (Throwable) {
            // l'installazione resta comunque bloccata dal file installed.lock
        }

        return view('install.done', [
            'version' => $result['version'],
            'vapid' => $result['vapid'],
            'tokenRemoved' => $tokenRemoved,
            'email' => $data['email'],
        ]);
    }
}
