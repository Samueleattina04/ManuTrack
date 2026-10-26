<?php

namespace App\Http\Controllers;

use App\Actions\Users\AcceptInvitation;
use App\Models\Invitation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Pagina raggiunta dal link nell'email di invito: la persona sceglie
 * nome e password ed entra con il ruolo deciso dall'amministratore.
 */
class InvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = Invitation::findByToken($token);

        return view('auth.invitation', [
            'invitation' => $invitation?->isPending() ? $invitation : null,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token, AcceptInvitation $accept): RedirectResponse
    {
        $invitation = Invitation::findByToken($token);
        abort_unless($invitation && $invitation->isPending(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $accept->handle($invitation, $data['name'], $data['password']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', __('users.invite.welcome', ['name' => $user->name]));
    }
}
