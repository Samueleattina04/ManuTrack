<?php

namespace App\Livewire\Kpi;

use App\Models\AssetCategory;
use App\Models\Location;
use App\Support\Kpi\KpiCalculator;
use App\Support\Kpi\KpiPeriod;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard degli indicatori. Definizioni e regole in docs/KPI.md.
 */
class Dashboard extends Component
{
    #[Url(as: 'periodo')]
    public string $preset = '30g';

    #[Url(as: 'dal')]
    public string $from = '';

    #[Url(as: 'al')]
    public string $to = '';

    #[Url(as: 'ubicazione')]
    public string $location = '';

    #[Url(as: 'categoria')]
    public string $category = '';

    public function mount(): void
    {
        $this->authorize('reports.view');
    }

    public function choose(string $preset): void
    {
        $this->preset = in_array($preset, KpiPeriod::PRESETS, true) ? $preset : '30g';
        $this->from = $this->to = '';
    }

    public function render(): View
    {
        $error = null;
        try {
            $period = $this->preset === 'scelta' && $this->from !== '' && $this->to !== ''
                ? KpiPeriod::between($this->from, $this->to)
                : KpiPeriod::preset($this->preset);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            $period = KpiPeriod::preset('30g');
        }
        if ($this->preset !== 'scelta') {
            $this->from = $period->from->toDateString();
            $this->to = $period->to->toDateString();
        }

        $kpi = new KpiCalculator($period, ctype_digit($this->location) ? (int) $this->location : null, ctype_digit($this->category) ? (int) $this->category : null);
        $canCosts = auth()->user()->can('work_orders.view_costs');

        return view('livewire.kpi.dashboard', [
            'period' => $period,
            'error' => $error,
            'downtime' => $kpi->downtime(),
            'mttr' => $kpi->mttr(),
            'mtbf' => $kpi->mtbf(),
            'backlog' => $kpi->backlog(),
            'mix' => $kpi->mix(),
            'compliance' => $kpi->compliance(),
            'costs' => $canCosts ? $kpi->costs() : null,
            'assets' => $kpi->byAsset(),
            'canCosts' => $canCosts,
            'locations' => Location::labels(),
            'categories' => AssetCategory::query()->orderBy('name')->pluck('name', 'id'),
            'presets' => KpiPeriod::PRESETS,
        ])->title(__('kpi.title'))->layoutData(['heading' => __('kpi.title')]);
    }
}
