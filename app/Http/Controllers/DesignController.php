<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\Priority;
use Illuminate\Contracts\View\View;

/**
 * Guida di stile dal vivo: token, logo, componenti (vedi docs/DESIGN.md).
 */
class DesignController extends Controller
{
    public function __invoke(): View
    {
        // Pagina per chi sviluppa: in produzione non esiste
        abort_if(app()->isProduction(), 404);

        return view('design', [
            'statuses' => AssetStatus::cases(),
            'priorities' => Priority::cases(),
            'palettes' => config('manutrack.palettes'),
        ]);
    }
}
