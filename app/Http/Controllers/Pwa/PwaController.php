<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Support\Push\PushSender;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Procedura guidata (installazione e notifiche) e pagina diagnostica.
 */
class PwaController extends Controller
{
    public function install(Request $request, PushSender $sender): View
    {
        return view('pwa.install', [
            'pushConfigured' => $sender->configured(),
            'devices' => PushSubscription::query()->where('user_id', $request->user()->id)->count(),
        ]);
    }

    public function diagnostics(Request $request, PushSender $sender): View
    {
        $devices = PushSubscription::query()->where('user_id', $request->user()->id)->latest('last_used_at')->get();

        return view('pwa.diagnostics', [
            'pushConfigured' => $sender->configured(),
            'devices' => $devices,
            'endpointHashes' => $devices->pluck('endpoint_hash')->all(),
            'serverHttps' => $request->isSecure(),
            'appUrl' => config('app.url'),
            'pushHosts' => array_values(array_map('strtolower', (array) config('manutrack.webpush.hosts'))),
        ]);
    }
}
