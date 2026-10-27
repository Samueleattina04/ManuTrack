<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Support\Setup\SystemStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request, SystemStatus $status): View
    {
        $canSeeAssets = $request->user()->can('assets.view');

        return view('home', [
            'status' => $status->summary(),
            // Macchine ferme o in guasto: prima le critiche, poi le più vecchie
            'down' => $canSeeAssets
                ? Asset::query()->with(['location', 'category', 'cover'])
                    ->whereIn('status', [AssetStatus::Fault->value, AssetStatus::Stopped->value])
                    ->orderBy('criticality')->orderBy('status_changed_at')
                    ->limit(6)->get()
                : null,
            'assetCount' => $canSeeAssets ? Asset::query()->count() : 0,
        ]);
    }
}
