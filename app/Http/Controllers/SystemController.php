<?php

namespace App\Http\Controllers;

use App\Support\Setup\AppUrlCheck;
use App\Support\Setup\SystemStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Pagina Sistema: versione, aggiornamenti, copie del database, requisiti, cron.
 * Dalla fase 2 è riservata agli amministratori e permette di ritentare un aggiornamento.
 */
class SystemController extends Controller
{
    public function __invoke(SystemStatus $status, Request $request): View
    {
        return view('system', [
            'status' => $status->summary(),
            'history' => $status->history(),
            'backups' => $status->backups(),
            'checks' => $status->checks(),
            'appUrlProblems' => AppUrlCheck::problems($request),
        ]);
    }
}
