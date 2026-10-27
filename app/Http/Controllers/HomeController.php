<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\RequestStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\WorkOrder;
use App\Models\WorkRequest;
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
            // Da valutare: prima le urgenti
            'toTriage' => $request->user()->can('requests.triage')
                ? WorkRequest::query()->with('asset')->where('status', RequestStatus::New->value)
                    ->orderByRaw("case priority when 'critica' then 0 when 'alta' then 1 else 2 end")->orderByDesc('machine_stopped')->latest('reported_at')->limit(5)->get()
                : null,
            'triageCount' => $request->user()->can('requests.triage') ? WorkRequest::query()->where('status', RequestStatus::New->value)->count() : 0,
            // I miei ordini ancora da fare
            'myOrders' => $request->user()->can('work_orders.execute')
                ? WorkOrder::query()->assignedTo($request->user())->whereIn('status', array_map(fn ($s) => $s->value, WorkOrderStatus::active()))->with('asset')->byUrgency()->limit(6)->get()
                : null,
            // Preventiva: piani scaduti o in scadenza, per chi la gestisce
            'duePlans' => $request->user()->can('preventive.manage')
                ? MaintenancePlan::query()->active()->with(['asset', 'meter', 'currentOrder'])->get()
                    ->map(fn (MaintenancePlan $p) => ['plan' => $p, 'state' => $p->dueState()])
                    ->filter(fn ($r) => in_array($r['state'], ['scaduto', 'in_scadenza'], true))
                    ->sortBy(fn ($r) => [$r['state'] === 'scaduto' ? 0 : 1, $r['plan']->next_due_on?->getTimestamp() ?? PHP_INT_MAX])
                    ->values()
                : null,
            // Per il magazziniere: ricambi da riordinare, prima gli esauriti
            'lowStock' => $request->user()->can('inventory.manage')
                ? Part::query()->where('is_active', true)->belowMinimum()->orderBy('stock_total')->orderBy('code')->limit(6)->get()
                : null,
            'lowStockCount' => $request->user()->can('inventory.manage') ? Part::query()->where('is_active', true)->belowMinimum()->count() : 0,
        ]);
    }
}
