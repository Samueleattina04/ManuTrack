<?php

namespace App\Http\Controllers;

use App\Support\Kpi\MonthlyReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Rapporto mensile stampabile: una pagina HTML con il CSS di stampa, da
 * stampare o salvare in PDF dal browser (nessun programma sul server).
 */
class ReportController extends Controller
{
    public function monthly(Request $request): View
    {
        try {
            $month = MonthlyReport::monthFrom($request->query('mese') === null ? null : (string) $request->query('mese'));
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $report = new MonthlyReport($month, $request->user());

        return view('reports.monthly', [
            ...$report->data(),
            'company' => (string) settings('company.name', config('app.name')),
            'prev' => $month->subMonthNoOverflow(),
            'next' => $month->addMonthNoOverflow()->lte(now()->startOfMonth()) ? $month->addMonthNoOverflow() : null,
        ]);
    }
}
