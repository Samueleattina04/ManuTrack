<?php

namespace App\Support\Kpi;

use App\Enums\AssetStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Asset;
use App\Models\DowntimeEvent;
use App\Models\Location;
use App\Models\WorkOrder;
use App\Support\Preventive\PlanCompliance;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Calcolo degli indicatori. Ogni metodo segue la definizione di docs/KPI.md:
 * se cambi una formula qui, cambiala anche là (e nei test con i risultati a mano).
 *
 * Le durate sono in ore, calcolate sui timestamp (tempo reale trascorso).
 * "insufficient" = true significa che il valore non va mostrato (§1.6).
 */
class KpiCalculator
{
    /** Minimi per mostrare un valore (docs/KPI.md §1.6) */
    public const MIN_MTTR_EVENTS = 3;

    public const MIN_MTBF_FAILURES = 3;

    public const MIN_MTBF_OBSERVED_HOURS = 7 * 24;

    public const MIN_MIX_ORDERS = 5;

    public const MIN_COMPLIANCE = 3;

    /** @var list<int>|null macchine dei filtri (null = nessun filtro) */
    private ?array $assetIds = null;

    /** @var Collection<int, DowntimeEvent>|null */
    private ?Collection $events = null;

    public function __construct(private KpiPeriod $period, private ?int $locationId = null, private ?int $categoryId = null)
    {
        if ($locationId || $categoryId) {
            $location = $locationId ? Location::query()->find($locationId) : null;
            $this->assetIds = Asset::query()
                ->when($location, fn (Builder $q) => $q->whereIn('location_id', $location->branchIds()))
                ->when($locationId && ! $location, fn (Builder $q) => $q->whereRaw('1 = 0'))
                ->when($categoryId, fn (Builder $q) => $q->where('asset_category_id', $categoryId))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
    }

    public function period(): KpiPeriod
    {
        return $this->period;
    }

    // ---------- Andamento nel tempo (grafici) ----------

    /**
     * Il periodo diviso in intervalli (settimane fino a 100 giorni, altrimenti mesi),
     * ognuno calcolato con le stesse regole e gli stessi minimi (docs/KPI.md §2.8).
     *
     * @return array{unit: string, buckets: list<array{label: string, from: string, to: string, downtime_count: int, downtime_hours: float, mttr: float|null, mttr_n: int, preventive: int, corrective: int}>}
     */
    public function series(): array
    {
        $days = (int) $this->period->from->diffInDays($this->period->to) + 1;
        $unit = $days <= 100 ? 'week' : 'month';
        $buckets = [];
        $cursor = $this->period->from;
        while ($cursor->lte($this->period->to)) {
            $end = $unit === 'week' ? $cursor->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay() : $cursor->endOfMonth()->startOfDay();
            $end = $end->gt($this->period->to) ? $this->period->to : $end;
            if ($cursor->gt($this->period->now)) {
                break;
            }
            $kpi = new self(KpiPeriod::between($cursor->toDateString(), $end->toDateString(), $this->period->now), $this->locationId, $this->categoryId);
            $down = $kpi->downtime();
            $mttr = $kpi->mttr();
            $mix = $kpi->mix();
            $buckets[] = [
                'label' => $unit === 'week' ? $cursor->translatedFormat('j M') : $cursor->translatedFormat('M y'),
                'from' => $cursor->toDateString(),
                'to' => $end->toDateString(),
                'downtime_count' => $down['count'],
                'downtime_hours' => round($down['hours'], 1),
                'mttr' => $mttr['value'] === null ? null : round($mttr['value'], 1),
                'mttr_n' => $mttr['n'],
                'preventive' => $mix['preventive'],
                'corrective' => $mix['corrective'],
            ];
            $cursor = $end->addDay();
        }

        return ['unit' => $unit, 'buckets' => $buckets];
    }

    // ---------- Fermi ----------

    /**
     * Fermi che si sovrappongono al periodo, con il tipo (guasto o pianificato).
     *
     * @return Collection<int, DowntimeEvent>
     */
    private function overlapping(): Collection
    {
        return $this->events ??= DowntimeEvent::query()
            ->with('workOrder:id,type')
            ->when($this->assetIds !== null, fn (Builder $q) => $q->whereIn('asset_id', $this->assetIds))
            ->where('started_at', '<', $this->period->end)
            ->where(fn (Builder $q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $this->period->start))
            ->get();
    }

    /** Fermo pianificato = collegato a un ordine preventivo o di miglioramento (§1.5) */
    public static function isPlanned(DowntimeEvent $e): bool
    {
        return $e->workOrder !== null && in_array($e->workOrder->type, [WorkOrderType::Preventive, WorkOrderType::Improvement], true);
    }

    /** Ore del fermo dentro [from, to) (fermo aperto = fino a to) */
    private static function clippedHours(DowntimeEvent $e, CarbonInterface $from, CarbonInterface $to): float
    {
        $s = max($e->started_at->getTimestamp(), $from->getTimestamp());
        $f = min($e->ended_at?->getTimestamp() ?? $to->getTimestamp(), $to->getTimestamp());

        return max(0, $f - $s) / 3600;
    }

    /**
     * §2.1 Numero e durata dei fermi.
     *
     * @return array{count: int, hours: float, failure_count: int, failure_hours: float, planned_count: int, planned_hours: float, open: int}
     */
    public function downtime(?int $assetId = null): array
    {
        $out = ['count' => 0, 'hours' => 0.0, 'failure_count' => 0, 'failure_hours' => 0.0, 'planned_count' => 0, 'planned_hours' => 0.0, 'open' => 0];
        foreach ($this->overlapping() as $e) {
            if ($assetId !== null && $e->asset_id !== $assetId) {
                continue;
            }
            $h = self::clippedHours($e, $this->period->start, $this->period->end);
            $kind = self::isPlanned($e) ? 'planned' : 'failure';
            $out['count']++;
            $out['hours'] += $h;
            $out[$kind.'_count']++;
            $out[$kind.'_hours'] += $h;
            if ($e->ended_at === null) {
                $out['open']++;
            }
        }

        return $out;
    }

    /**
     * §2.2 MTTR: fermi per guasto conclusi nel periodo, durata intera.
     *
     * @return array{value: float|null, n: int, insufficient: bool}
     */
    public function mttr(?int $assetId = null): array
    {
        $events = DowntimeEvent::query()->with('workOrder:id,type')
            ->when($this->assetIds !== null, fn (Builder $q) => $q->whereIn('asset_id', $this->assetIds))
            ->when($assetId !== null, fn (Builder $q) => $q->where('asset_id', $assetId))
            ->whereNotNull('ended_at')
            ->where('ended_at', '>=', $this->period->start)->where('ended_at', '<', $this->period->end)
            ->get()
            ->reject(fn (DowntimeEvent $e) => self::isPlanned($e));

        $n = $events->count();
        $hours = $events->sum(fn (DowntimeEvent $e) => ($e->ended_at->getTimestamp() - $e->started_at->getTimestamp()) / 3600);
        $insufficient = $n < self::MIN_MTTR_EVENTS;

        return ['value' => $insufficient ? null : $hours / $n, 'n' => $n, 'insufficient' => $insufficient];
    }

    /**
     * §2.3 MTBF: funzionamento / guasti, sulle macchine non dismesse.
     *
     * @return array{value: float|null, failures: int, observed_hours: float, uptime_hours: float, assets: int, insufficient: bool}
     */
    public function mtbf(?int $assetId = null): array
    {
        $assets = Asset::query()
            ->where('status', '!=', AssetStatus::Retired->value)
            ->when($this->assetIds !== null, fn (Builder $q) => $q->whereIn('id', $this->assetIds))
            ->when($assetId !== null, fn (Builder $q) => $q->whereKey($assetId))
            ->get(['id', 'installed_on', 'created_at']);
        $events = $this->overlapping()->groupBy('asset_id');

        $observed = 0.0;
        $uptime = 0.0;
        $failures = 0;
        $counted = 0;
        foreach ($assets as $asset) {
            $history = $asset->installed_on ? CarbonImmutable::parse($asset->installed_on->toDateString())->startOfDay() : CarbonImmutable::parse($asset->created_at);
            $from = $history->gt($this->period->start) ? $history : $this->period->start;
            $to = $this->period->end;
            if ($from->gte($to)) {
                continue; // senza storico nel periodo
            }
            $counted++;
            $window = ($to->getTimestamp() - $from->getTimestamp()) / 3600;
            $down = 0.0;
            foreach ($events->get($asset->id, collect()) as $e) {
                $down += self::clippedHours($e, $from, $to);
                if (! self::isPlanned($e) && $e->started_at->gte($from) && $e->started_at->lt($to)) {
                    $failures++;
                }
            }
            $observed += $window;
            $uptime += max(0, $window - $down);
        }

        $insufficient = $failures < self::MIN_MTBF_FAILURES || $observed < self::MIN_MTBF_OBSERVED_HOURS;

        return [
            'value' => $insufficient ? null : $uptime / $failures,
            'failures' => $failures,
            'observed_hours' => $observed,
            'uptime_hours' => $uptime,
            'assets' => $counted,
            'insufficient' => $insufficient,
        ];
    }

    // ---------- Ordini ----------

    /** @return Builder<WorkOrder> */
    private function orders(): Builder
    {
        return WorkOrder::query()->when($this->assetIds !== null, fn (Builder $q) => $q->whereIn('asset_id', $this->assetIds));
    }

    /**
     * Ordini completati nel periodo, con la stessa regola degli indicatori (per il rapporto mensile).
     *
     * @return Builder<WorkOrder>
     */
    public function completedOrders(): Builder
    {
        return $this->completed();
    }

    /**
     * Ordini completati nel periodo (§2.5): completed_at in [inizio, fine), stato completato o chiuso.
     *
     * @return Builder<WorkOrder>
     */
    private function completed(): Builder
    {
        return $this->orders()
            ->whereIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Closed->value])
            ->where('completed_at', '>=', $this->period->start)->where('completed_at', '<', $this->period->end);
    }

    /**
     * §2.4 Arretrato: istantanea di adesso.
     *
     * @return array{open: int, overdue: int, avg_age_days: float|null, by_priority: array<string, int>, by_type: array<string, int>}
     */
    public function backlog(): array
    {
        $now = $this->period->now;
        $open = $this->orders()->whereIn('status', array_map(fn ($s) => $s->value, WorkOrderStatus::active()))->get(['id', 'type', 'priority', 'due_at', 'created_at']);

        return [
            'open' => $open->count(),
            'overdue' => $open->filter(fn (WorkOrder $o) => $o->due_at !== null && $o->due_at->lt($now))->count(),
            'avg_age_days' => $open->isEmpty() ? null : $open->avg(fn (WorkOrder $o) => ($now->getTimestamp() - $o->created_at->getTimestamp()) / 86400),
            'by_priority' => $open->countBy(fn (WorkOrder $o) => $o->priority->value)->all(),
            'by_type' => $open->countBy(fn (WorkOrder $o) => $o->type->value)->all(),
        ];
    }

    /**
     * §2.5 Percentuale preventivo / correttivo.
     *
     * @return array{preventive: int, corrective: int, improvement: int, preventive_pct: float|null, corrective_pct: float|null, insufficient: bool}
     */
    public function mix(): array
    {
        $counts = $this->completed()->get(['id', 'type'])->countBy(fn (WorkOrder $o) => $o->type->value);
        $p = (int) ($counts[WorkOrderType::Preventive->value] ?? 0);
        $c = (int) ($counts[WorkOrderType::Corrective->value] ?? 0);
        $insufficient = $p + $c < self::MIN_MIX_ORDERS;
        $pct = $insufficient ? null : $p / ($p + $c) * 100;

        return [
            'preventive' => $p,
            'corrective' => $c,
            'improvement' => (int) ($counts[WorkOrderType::Improvement->value] ?? 0),
            'preventive_pct' => $pct,
            'corrective_pct' => $pct === null ? null : 100 - $pct,
            'insufficient' => $insufficient,
        ];
    }

    /**
     * §2.6 Costi per macchina degli ordini completati nel periodo.
     *
     * @return array{rows: list<array{asset_id: int|null, labor: float, parts: float, external: float, total: float, orders: int}>, total: array{labor: float, parts: float, external: float, total: float, orders: int}}
     */
    public function costs(): array
    {
        $rows = [];
        $total = ['labor' => 0.0, 'parts' => 0.0, 'external' => 0.0, 'total' => 0.0, 'orders' => 0];
        foreach ($this->completed()->with(['timeEntries', 'stockMovements'])->get() as $order) {
            $c = $order->costs();
            $key = $order->asset_id ?? 0;
            $rows[$key] ??= ['asset_id' => $order->asset_id, 'labor' => 0.0, 'parts' => 0.0, 'external' => 0.0, 'total' => 0.0, 'orders' => 0];
            foreach (['labor', 'parts', 'external', 'total'] as $k) {
                $rows[$key][$k] += $c[$k];
                $total[$k] += $c[$k];
            }
            $rows[$key]['orders']++;
            $total['orders']++;
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * §2.7 Rispetto dei piani.
     *
     * @return array{on_time: int, late: int, skipped: int, overdue: int, total: int, rate: float|null, insufficient: bool}
     */
    public function compliance(): array
    {
        $summary = app(PlanCompliance::class)->summary($this->period->from, $this->period->lastDueDay(), null, $this->assetIds);
        $insufficient = $summary['total'] < self::MIN_COMPLIANCE;

        return ['rate' => $insufficient ? null : $summary['rate']] + $summary + ['insufficient' => $insufficient];
    }

    /**
     * Tabella per macchina: fermi, MTTR, MTBF (con gli stessi minimi) e costi.
     *
     * @return list<array<string, mixed>>
     */
    public function byAsset(): array
    {
        $costs = collect($this->costs()['rows'])->keyBy(fn ($r) => $r['asset_id'] ?? 0);
        $ids = $this->overlapping()->pluck('asset_id')->merge($costs->keys()->filter())->unique()->filter()->values();
        $assets = Asset::query()->whereIn('id', $ids)->orderBy('code')->get(['id', 'code', 'name', 'status']);

        return $assets->map(fn (Asset $a) => [
            'asset' => $a,
            'downtime' => $this->downtime($a->id),
            'mttr' => $this->mttr($a->id),
            'mtbf' => $a->status === AssetStatus::Retired ? null : $this->mtbf($a->id),
            'cost' => $costs->get($a->id),
        ])->sortByDesc(fn ($r) => $r['downtime']['hours'])->values()->all();
    }
}
