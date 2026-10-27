<?php

namespace App\Support\Export;

use App\Models\Asset;
use App\Models\Location;
use App\Models\Part;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Filters\AssetFilters;
use App\Support\Filters\PartFilters;
use App\Support\Filters\WorkOrderFilters;
use App\Support\Kpi\KpiCalculator;
use Carbon\CarbonImmutable;

/**
 * Le esportazioni: stesse righe degli elenchi (filtri compresi), colonne dei
 * costi solo a chi ha il permesso di vederli.
 */
final class Exports
{
    /** Righe massime di un'esportazione: oltre, si chiede di restringere i filtri */
    public const MAX_ROWS = 20000;

    public function workOrders(User $user, WorkOrderFilters $filters): Table
    {
        $costs = $user->can('work_orders.view_costs');
        $c = fn (string $k) => __('export.col.'.$k);
        $columns = [
            $c('number') => 'text', $c('type') => 'text', $c('title') => 'text', $c('status') => 'text', $c('priority') => 'text',
            $c('asset_code') => 'text', $c('asset_name') => 'text', $c('location') => 'text', $c('assignees') => 'text', $c('plan') => 'text',
            $c('created_at') => 'datetime', $c('due_at') => 'datetime', $c('started_at') => 'datetime', $c('completed_at') => 'datetime', $c('closed_at') => 'datetime',
            $c('labor_minutes') => 'int',
        ];
        if ($costs) {
            $columns += [$c('labor_cost') => 'decimal', $c('parts_cost') => 'decimal', $c('external_cost') => 'decimal', $c('total_cost') => 'decimal'];
        }

        $query = $filters->query($user)->with(['asset', 'location', 'assignees', 'plan', 'timeEntries', ...($costs ? ['stockMovements'] : [])]);
        $locations = Location::labels();

        return new Table(__('export.files.ordini'), $columns, function () use ($query, $costs, $locations) {
            foreach ($query->lazy(500) as $wo) {
                /** @var WorkOrder $wo */
                $row = [
                    $wo->number, $wo->type->label(), $wo->title, $wo->status->label(), $wo->priority->label(),
                    $wo->asset?->code, $wo->asset?->name,
                    $locations[$wo->location_id ?? $wo->asset->location_id ?? 0] ?? null,
                    $wo->assignees->pluck('name')->implode(', '),
                    $wo->plan?->title,
                    $wo->created_at, $wo->due_at, $wo->started_at, $wo->completed_at, $wo->closed_at,
                    $wo->laborMinutes(),
                ];
                if ($costs) {
                    $k = $wo->costs();
                    array_push($row, $k['labor'], $k['parts'], $k['external'], $k['total']);
                }
                yield $row;
            }
        });
    }

    public function assets(User $user, AssetFilters $filters): Table
    {
        $costs = $user->can('work_orders.view_costs');
        $c = fn (string $k) => __('export.col.'.$k);
        $columns = [
            $c('code') => 'text', $c('name') => 'text', $c('category') => 'text', $c('location') => 'text', $c('status') => 'text', $c('criticality') => 'text',
            $c('serial') => 'text', $c('manufacturer') => 'text', $c('model') => 'text', $c('year') => 'int',
            $c('installed_on') => 'date', $c('warranty_until') => 'date',
        ];
        if ($costs) {
            $columns[$c('purchase_cost')] = 'decimal';
        }
        $query = $filters->sorted()->with('category');
        $locations = Location::labels();

        return new Table(__('export.files.macchine'), $columns, function () use ($query, $costs, $locations) {
            foreach ($query->lazy(500) as $a) {
                /** @var Asset $a */
                $row = [
                    $a->code, $a->name, $a->category?->name, $locations[$a->location_id ?? 0] ?? null, $a->status->label(), $a->criticality->label(),
                    $a->serial_number, $a->manufacturer, $a->model, $a->year, $a->installed_on, $a->warranty_until,
                ];
                if ($costs) {
                    $row[] = $a->purchase_cost;
                }
                yield $row;
            }
        });
    }

    public function parts(User $user, PartFilters $filters): Table
    {
        $costs = $user->can('inventory.view_costs');
        $c = fn (string $k) => __('export.col.'.$k);
        $columns = [
            $c('code') => 'text', $c('name') => 'text', $c('category') => 'text', $c('unit') => 'text', $c('manufacturer') => 'text', $c('manufacturer_code') => 'text',
            $c('stock_total') => 'quantity', $c('min_stock') => 'quantity', $c('reorder_qty') => 'quantity', $c('stock_state') => 'text', $c('levels') => 'text',
        ];
        if ($costs) {
            $columns += [$c('unit_cost') => 'decimal', $c('stock_value') => 'decimal'];
        }
        $query = $filters->query()->with('levels.warehouse')->orderBy('code');

        return new Table(__('export.files.ricambi'), $columns, function () use ($query, $costs) {
            foreach ($query->lazy(500) as $p) {
                /** @var Part $p */
                $levels = $p->levels
                    ->filter(fn (StockLevel $l) => (float) $l->quantity != 0.0)
                    ->map(fn (StockLevel $l) => $l->warehouse->name.': '.rtrim(rtrim(number_format((float) $l->quantity, 3, ',', ''), '0'), ','))
                    ->implode(' · ');
                $row = [
                    $p->code, $p->name, $p->category, $p->unit, $p->manufacturer, $p->manufacturer_code,
                    $p->stock_total, $p->min_stock, $p->reorder_qty, __('inventory.state.'.$p->stockState()), $levels,
                ];
                if ($costs) {
                    array_push($row, $p->unit_cost, round((float) $p->unit_cost * (float) $p->stock_total, 2));
                }
                yield $row;
            }
        });
    }

    /**
     * Indicatori: riepilogo, per macchina, andamento e (con il permesso) costi.
     *
     * @return array<string, Table>
     */
    public function kpi(User $user, KpiCalculator $kpi, string $filtersLabel): array
    {
        $costs = $user->can('work_orders.view_costs');
        $c = fn (string $k) => __('export.col.'.$k);
        $k = fn (string $key, array $r = []) => __('export.kpi.'.$key, $r);
        $period = $kpi->period();
        $on = fn (int $n) => $k('on_events', ['n' => $n]);
        $or = fn (array $m, ?string $empty = null) => $m['insufficient'] ? ($empty ?? $k('insufficient')) : null;

        $downtime = $kpi->downtime();
        $mttr = $kpi->mttr();
        $mtbf = $kpi->mtbf();
        $backlog = $kpi->backlog();
        $mix = $kpi->mix();
        $compliance = $kpi->compliance();

        $summary = [
            [$k('period'), null, null, null, $period->from->format('d/m/Y').' – '.$period->to->format('d/m/Y').' ('.config('app.timezone').')'],
            [$k('filters'), null, null, null, $filtersLabel],
            [$k('downtime_count'), $downtime['count'], $k('unit_n'), null, null],
            [$k('downtime_hours'), round($downtime['hours'], 1), $k('unit_hours'), null, null],
            [$k('failure_hours'), round($downtime['failure_hours'], 1), $k('unit_hours'), $on($downtime['failure_count']), null],
            [$k('planned_hours'), round($downtime['planned_hours'], 1), $k('unit_hours'), $on($downtime['planned_count']), null],
            [$k('open'), $downtime['open'], $k('unit_n'), null, null],
            [$k('mttr'), $mttr['insufficient'] ? null : round((float) $mttr['value'], 1), $k('unit_hours'), $on($mttr['n']), $or($mttr)],
            [$k('mtbf'), $mtbf['value'] === null || $mtbf['insufficient'] ? null : round($mtbf['value'], 1), $k('unit_hours'), $on($mtbf['failures']), $mtbf['failures'] === 0 ? $k('no_failures') : $or($mtbf)],
            [$k('backlog'), $backlog['open'], $k('unit_n'), null, null],
            [$k('overdue'), $backlog['overdue'], $k('unit_n'), null, null],
            [$k('age'), $backlog['avg_age_days'] === null ? null : round($backlog['avg_age_days'], 1), $k('unit_days'), null, null],
            [$k('preventive_pct'), $mix['insufficient'] ? null : $mix['preventive_pct'], $k('unit_pct'), $on($mix['preventive'] + $mix['corrective']), $or($mix)],
            [$k('corrective_pct'), $mix['insufficient'] ? null : $mix['corrective_pct'], $k('unit_pct'), $on($mix['preventive'] + $mix['corrective']), $or($mix)],
            [$k('improvement'), $mix['improvement'], $k('unit_n'), null, null],
            [$k('compliance'), $compliance['insufficient'] ? null : $compliance['rate'], $k('unit_pct'), $on($compliance['total']), $or($compliance)],
        ];
        $costData = $costs ? $kpi->costs() : null;
        if ($costData !== null) {
            $summary[] = [$k('cost_total'), $costData['total']['total'], $k('unit_eur'), $on($costData['total']['orders']), null];
        }

        $tables = [
            'riepilogo' => new Table(__('export.kpi_parts.riepilogo'), [$c('indicator') => 'text', $c('value') => 'number', $c('unit_of') => 'text', $c('basis') => 'text', $c('note') => 'text'], $summary),
        ];

        $assetColumns = [
            $c('code') => 'text', $c('name') => 'text', $c('downtime_count') => 'int', $c('downtime_hours') => 'hours', $c('failure_count') => 'int', $c('failure_hours') => 'hours',
            $c('mttr_hours') => 'hours', $c('mttr_n') => 'int', $c('mtbf_hours') => 'hours', $c('failures') => 'int', $c('note') => 'text',
        ];
        if ($costs) {
            $assetColumns += [$c('orders') => 'int', $c('total_cost') => 'decimal'];
        }
        $tables['macchine'] = new Table(__('export.kpi_parts.macchine'), $assetColumns, function () use ($kpi, $costs, $k) {
            foreach ($kpi->byAsset() as $r) {
                $notes = array_filter([
                    $r['mttr']['insufficient'] ? 'MTTR '.$k('insufficient') : null,
                    $r['mtbf'] === null ? __('kpi.retired') : ($r['mtbf']['failures'] === 0 ? 'MTBF '.$k('no_failures') : ($r['mtbf']['insufficient'] ? 'MTBF '.$k('insufficient') : null)),
                ]);
                $row = [
                    $r['asset']->code, $r['asset']->name, $r['downtime']['count'], round($r['downtime']['hours'], 1), $r['downtime']['failure_count'], round($r['downtime']['failure_hours'], 1),
                    $r['mttr']['insufficient'] ? null : round((float) $r['mttr']['value'], 1), $r['mttr']['n'],
                    $r['mtbf'] === null || $r['mtbf']['insufficient'] || $r['mtbf']['value'] === null ? null : round($r['mtbf']['value'], 1), $r['mtbf']['failures'] ?? null,
                    implode(' · ', $notes),
                ];
                if ($costs) {
                    array_push($row, $r['cost']['orders'] ?? 0, $r['cost']['total'] ?? 0);
                }
                yield $row;
            }
        });

        $tables['andamento'] = new Table(__('export.kpi_parts.andamento'), [
            $c('bucket') => 'text', $c('from') => 'date', $c('to') => 'date', $c('downtime_count') => 'int', $c('downtime_hours') => 'hours',
            $c('mttr_hours') => 'hours', $c('mttr_n') => 'int', $c('preventive') => 'int', $c('corrective') => 'int',
        ], function () use ($kpi) {
            foreach ($kpi->series()['buckets'] as $b) {
                yield [
                    $b['label'], CarbonImmutable::parse($b['from']), CarbonImmutable::parse($b['to']), $b['downtime_count'], $b['downtime_hours'],
                    $b['mttr'], $b['mttr_n'], $b['preventive'], $b['corrective'],
                ];
            }
        });

        if ($costData !== null) {
            $assets = Asset::query()->whereIn('id', array_filter(array_column($costData['rows'], 'asset_id')))->get(['id', 'code', 'name'])->keyBy('id');
            $tables['costi'] = new Table(__('export.kpi_parts.costi'), [
                $c('code') => 'text', $c('name') => 'text', $c('orders') => 'int', $c('labor_cost') => 'decimal', $c('parts_cost') => 'decimal', $c('external_cost') => 'decimal', $c('total_cost') => 'decimal',
            ], function () use ($costData, $assets, $k) {
                foreach ($costData['rows'] as $r) {
                    $a = $r['asset_id'] !== null ? $assets->get($r['asset_id']) : null;
                    yield [$a?->code, $a->name ?? $k('without_asset'), $r['orders'], $r['labor'], $r['parts'], $r['external'], $r['total']];
                }
                $t = $costData['total'];
                yield [null, __('kpi.costs.total'), $t['orders'], $t['labor'], $t['parts'], $t['external'], $t['total']];
            });
        }

        return $tables;
    }
}
