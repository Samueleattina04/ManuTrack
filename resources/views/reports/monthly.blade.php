@php
    use App\Support\Kpi\KpiCalculator;
    $h = fn ($v) => number_format((float) $v, 1, ',', '.');
    $eur = fn ($v) => '€ '.number_format((float) $v, 2, ',', '.');
    $pct = fn ($v) => number_format((float) $v, 1, ',', '.').'%';
    $qty = fn ($v) => \App\Models\Part::formatQty($v);
    $monthLabel = ucfirst($month->translatedFormat('F Y'));
    $logo = \App\Support\Brand\CompanyLogo::url();
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('kpi.report.title') }} · {{ $monthLabel }} · {{ $company }}</title>
    <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        :root { color-scheme: light; --ink: #14181f; --muted: #5b6472; --line: #d5dae1; --soft: #f3f5f8; --ko: #b42318; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #e9ecf1; color: var(--ink); font: 14px/1.45 "IBM Plex Sans", system-ui, -apple-system, "Segoe UI", sans-serif; }
        .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: 8px 10px; align-items: center; padding: 10px 16px; background: #fff; border-bottom: 1px solid var(--line); }
        .bar a, .bar button { font: inherit; min-height: 44px; border-radius: 10px; border: 1px solid #c6ccd5; padding: 0 14px; background: #fff; color: var(--ink); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
        .bar button.primary { background: #1c2430; color: #fff; border-color: #1c2430; font-weight: 600; }
        .bar a[aria-disabled="true"] { opacity: .45; pointer-events: none; }
        .bar .hint { color: var(--muted); flex-basis: 100%; font-size: 13px; }
        .bar .grow { flex: 1; }
        .page { max-width: 210mm; margin: 16px auto; background: #fff; padding: 14mm 14mm 12mm; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
        header.top { display: flex; gap: 16px; align-items: flex-start; justify-content: space-between; border-bottom: 2px solid var(--ink); padding-bottom: 10px; margin-bottom: 14px; }
        header.top img { max-height: 48px; max-width: 60mm; object-fit: contain; }
        h1 { font-size: 20px; margin: 0; line-height: 1.2; }
        h1 small { display: block; font-size: 15px; font-weight: 600; color: var(--muted); margin-top: 2px; }
        h2 { font-size: 15px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid var(--line); break-after: avoid; }
        .meta { color: var(--muted); font-size: 12px; text-align: right; }
        .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(42mm, 1fr)); gap: 8px; }
        .tile { border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px; break-inside: avoid; }
        .tile .k { font-size: 12px; color: var(--muted); font-weight: 600; }
        .tile .v { font-size: 19px; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1.25; }
        .tile .v.na { font-size: 14px; color: var(--muted); }
        .tile .n { font-size: 11.5px; color: var(--muted); }
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { background: var(--soft); font-weight: 600; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .ko { color: var(--ko); font-weight: 600; }
        .muted, .empty { color: var(--muted); }
        .scroll { overflow-x: auto; }
        footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid var(--line); color: var(--muted); font-size: 11.5px; }
        @media (max-width: 640px) {
            .page { margin: 0; padding: 16px; box-shadow: none; }
            header.top { flex-direction: column; }
            .meta { text-align: left; }
            .tiles { grid-template-columns: 1fr 1fr; }
        }
        @media print {
            @page { size: A4 portrait; margin: 12mm 12mm 14mm; }
            html, body { background: #fff; font-size: 11pt; }
            .bar { display: none; }
            .page { margin: 0; padding: 0; box-shadow: none; max-width: none; }
            .scroll { overflow: visible; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
<nav class="bar" aria-label="{{ __('kpi.report.title') }}">
    <a href="{{ route('kpi') }}">← {{ __('kpi.report.back') }}</a>
    <a href="{{ route('kpi.report', ['mese' => $prev->format('Y-m')]) }}" aria-label="{{ __('kpi.report.prev') }}">‹ {{ ucfirst($prev->translatedFormat('M Y')) }}</a>
    @if($next)
        <a href="{{ route('kpi.report', ['mese' => $next->format('Y-m')]) }}" aria-label="{{ __('kpi.report.next') }}">{{ ucfirst($next->translatedFormat('M Y')) }} ›</a>
    @else
        <a aria-disabled="true">{{ __('kpi.report.next') }} ›</a>
    @endif
    <span class="grow"></span>
    <button type="button" class="primary" data-print>{{ __('kpi.report.print') }}</button>
    <span class="hint">{{ __('kpi.report.print_hint') }}</span>
</nav>

<main class="page">
    <header class="top">
        <div>
            <h1>{{ __('kpi.report.title') }}<small>{{ $monthLabel }}</small></h1>
        </div>
        <div class="meta">
            @if($logo)<img src="{{ $logo }}" alt="{{ $company }}"><br>@else<strong>{{ $company }}</strong><br>@endif
            {{ __('kpi.report.period', ['from' => $period->from->translatedFormat('j M Y'), 'to' => $period->to->translatedFormat('j M Y'), 'tz' => config('app.timezone')]) }}<br>
            {{ __('kpi.report.generated', ['at' => $generatedAt->translatedFormat('j M Y H:i'), 'tz' => config('app.timezone')]) }}
        </div>
    </header>

    <section aria-labelledby="r-kpi">
        <h2 id="r-kpi">{{ __('kpi.report.indicators') }}</h2>
        <div class="tiles">
            <div class="tile">
                <div class="k">{{ __('kpi.downtime.title') }}</div>
                <div class="v">{{ __('kpi.report.downtime_value', ['h' => $h($downtime['hours'])]) }}</div>
                <div class="n">{{ trans_choice('kpi.report.downtime_note', $downtime['count'], ['n' => $downtime['count']]) }}: {{ __('kpi.downtime.split', ['failures' => $downtime['failure_count'], 'fh' => $h($downtime['failure_hours']), 'planned' => $downtime['planned_count'], 'ph' => $h($downtime['planned_hours'])]) }}</div>
            </div>
            <div class="tile">
                <div class="k">{{ __('kpi.mttr.title') }}</div>
                @if($mttr['insufficient'])
                    <div class="v na">{{ __('kpi.insufficient') }}</div>
                    <div class="n">{{ trans_choice('kpi.mttr.need', $mttr['n'], ['n' => $mttr['n'], 'min' => KpiCalculator::MIN_MTTR_EVENTS]) }}</div>
                @else
                    <div class="v">{{ $h($mttr['value']) }} h</div>
                    <div class="n">{{ trans_choice('kpi.mttr.on', $mttr['n'], ['n' => $mttr['n']]) }}</div>
                @endif
            </div>
            <div class="tile">
                <div class="k">{{ __('kpi.mtbf.title') }}</div>
                @if($mtbf['work'])
                    {{-- Con un calendario: principale sulle ore di lavoro, sotto le 24 ore (docs/KPI.md §2.9) --}}
                    <div class="n">{{ $mtbf['work']['basis'] === 'lavoro' ? __('worktime.kpi.work_label') : __('worktime.kpi.mixed_label') }}</div>
                    @if($mtbf['failures'] === 0)
                        <div class="v na">{{ __('kpi.mtbf.no_failures') }}</div>
                    @elseif($mtbf['work']['insufficient'])
                        <div class="v na">{{ __('kpi.insufficient') }}</div>
                        <div class="n">{{ __('worktime.kpi.work_need', ['n' => $mtbf['failures'], 'hours' => $h($mtbf['work']['observed_hours']), 'min' => KpiCalculator::MIN_MTBF_FAILURES, 'minh' => KpiCalculator::MIN_MTBF_WORK_HOURS]) }}</div>
                    @else
                        <div class="v">{{ $h($mtbf['work']['value']) }} h</div>
                        <div class="n">{{ __('worktime.kpi.work_on', ['n' => $mtbf['failures'], 'hours' => $h($mtbf['work']['observed_hours'])]) }}</div>
                    @endif
                    <div class="n">{{ $mtbf['insufficient'] ? __('worktime.kpi.h24_insufficient') : __('worktime.kpi.h24_secondary', ['value' => $h($mtbf['value']).' h']) }}</div>
                @elseif($mtbf['failures'] === 0)
                    <div class="v na">{{ __('kpi.mtbf.no_failures') }}</div>
                @elseif($mtbf['insufficient'])
                    <div class="v na">{{ __('kpi.insufficient') }}</div>
                    <div class="n">{{ __('kpi.mtbf.need', ['n' => $mtbf['failures'], 'days' => $h($mtbf['observed_hours'] / 24), 'min' => KpiCalculator::MIN_MTBF_FAILURES]) }}</div>
                @else
                    <div class="v">{{ $h($mtbf['value']) }} h</div>
                    <div class="n">{{ __('kpi.mtbf.on', ['n' => $mtbf['failures'], 'assets' => $mtbf['assets'], 'days' => $h($mtbf['observed_hours'] / 24)]) }}</div>
                @endif
            </div>
            <div class="tile">
                <div class="k">{{ __('kpi.mix.title') }}</div>
                @if($mix['insufficient'])
                    <div class="v na">{{ __('kpi.insufficient') }}</div>
                    <div class="n">{{ __('kpi.mix.need', ['n' => $mix['preventive'] + $mix['corrective'], 'min' => KpiCalculator::MIN_MIX_ORDERS]) }}</div>
                @else
                    <div class="v">{{ $pct($mix['preventive_pct']) }} / {{ $pct($mix['corrective_pct']) }}</div>
                    <div class="n">{{ __('kpi.mix.preventive') }} {{ $mix['preventive'] }} · {{ __('kpi.mix.corrective') }} {{ $mix['corrective'] }} · {{ __('kpi.mix.improvement') }} {{ $mix['improvement'] }}</div>
                @endif
            </div>
            <div class="tile">
                <div class="k">{{ __('kpi.compliance.title') }}</div>
                @if($compliance['insufficient'])
                    <div class="v na">{{ __('kpi.insufficient') }}</div>
                    <div class="n">{{ __('kpi.compliance.need', ['n' => $compliance['total'], 'min' => KpiCalculator::MIN_COMPLIANCE]) }}</div>
                @else
                    <div class="v">{{ $pct($compliance['rate']) }}</div>
                    <div class="n">{{ __('preventive.compliance.on_time') }} {{ $compliance['on_time'] }} · {{ __('preventive.compliance.late') }} {{ $compliance['late'] }} · {{ __('preventive.compliance.skipped') }} {{ $compliance['skipped'] }} · {{ __('preventive.compliance.overdue') }} {{ $compliance['overdue'] }}</div>
                @endif
            </div>
            <div class="tile">
                <div class="k">{{ __('kpi.backlog.title') }}</div>
                <div class="v">{{ $backlog['open'] }}</div>
                <div class="n">{{ trans_choice('kpi.backlog.overdue', $backlog['overdue'], ['n' => $backlog['overdue']]) }} · {{ __('kpi.report.now_note') }}</div>
            </div>
            @if($costs)
                <div class="tile">
                    <div class="k">{{ __('kpi.costs.title') }}</div>
                    <div class="v">{{ $eur($costs['total']['total']) }}</div>
                    <div class="n">{{ __('kpi.costs.labor') }} {{ $eur($costs['total']['labor']) }} · {{ __('kpi.costs.parts') }} {{ $eur($costs['total']['parts']) }} · {{ __('kpi.costs.external') }} {{ $eur($costs['total']['external']) }}</div>
                </div>
            @endif
        </div>
    </section>

    @if($topAssets !== [])
        <section aria-labelledby="r-assets">
            <h2 id="r-assets">{{ __('kpi.report.top_assets') }}</h2>
            <div class="scroll"><table>
                <thead><tr><th>{{ __('kpi.report.col.asset') }}</th><th class="num">{{ __('kpi.report.col.downtime') }}</th><th class="num">{{ __('kpi.report.col.downtime_hours') }}</th><th class="num">{{ __('kpi.report.col.mttr') }}</th>@if($canCosts)<th class="num">{{ __('kpi.report.col.cost') }}</th>@endif</tr></thead>
                <tbody>
                @foreach($topAssets as $r)
                    <tr>
                        <td><strong>{{ $r['asset']->code }}</strong> {{ $r['asset']->name }}</td>
                        <td class="num">{{ $r['downtime']['count'] }}</td>
                        <td class="num">{{ $h($r['downtime']['hours']) }}</td>
                        <td class="num">{{ $r['mttr']['insufficient'] ? __('kpi.insufficient_short') : $h($r['mttr']['value']).' h' }}</td>
                        @if($canCosts)<td class="num">{{ $eur($r['cost']['total'] ?? 0) }}</td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </section>
    @endif

    @if($orders !== null)
        <section aria-labelledby="r-orders">
            <h2 id="r-orders">{{ __('kpi.report.orders') }} ({{ $ordersTotal }})</h2>
            <p class="muted" style="margin:0 0 6px;font-size:12px">{{ __('kpi.report.orders_hint') }}</p>
            @if($ordersTotal === 0)
                <p class="empty">{{ __('kpi.report.orders_none') }}</p>
            @else
                <div class="scroll"><table>
                    <thead><tr><th>{{ __('kpi.report.col.number') }}</th><th>{{ __('kpi.report.col.title') }}</th><th>{{ __('kpi.report.col.asset') }}</th><th>{{ __('kpi.report.col.type') }}</th><th class="num">{{ __('kpi.report.col.completed') }}</th><th class="num">{{ __('kpi.report.col.hours') }}</th>@if($canCosts)<th class="num">{{ __('kpi.report.col.cost') }}</th>@endif</tr></thead>
                    <tbody>
                    @foreach($orders as $o)
                        <tr>
                            <td class="num" style="text-align:left">{{ $o->number }}</td>
                            <td>{{ $o->title }}</td>
                            <td>{{ $o->asset?->code ?? '—' }}</td>
                            <td>{{ $o->type->label() }}</td>
                            <td class="num">{{ $o->completed_at?->translatedFormat('j M H:i') }}</td>
                            <td class="num">{{ $h($o->laborMinutes() / 60) }}</td>
                            @if($canCosts)<td class="num">{{ $eur($o->costs()['total']) }}</td>@endif
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                @if($ordersTotal > $orders->count())
                    <p class="muted">{{ __('kpi.report.orders_more', ['n' => $ordersTotal - $orders->count()]) }}</p>
                @endif
            @endif
        </section>
    @endif

    @if($lowStock !== null)
        <section aria-labelledby="r-stock">
            <h2 id="r-stock">{{ __('kpi.report.low_stock') }} ({{ $lowStock->count() }}) <span class="muted" style="font-weight:400;font-size:12px">· {{ __('kpi.report.now_note') }}</span></h2>
            @if($lowStock->isEmpty())
                <p class="empty">{{ __('kpi.report.low_stock_none') }}</p>
            @else
                <div class="scroll"><table>
                    <thead><tr><th>{{ __('kpi.report.col.code') }}</th><th>{{ __('kpi.report.col.part') }}</th><th class="num">{{ __('kpi.report.col.stock') }}</th><th class="num">{{ __('kpi.report.col.min') }}</th><th class="num">{{ __('kpi.report.col.order') }}</th></tr></thead>
                    <tbody>
                    @foreach($lowStock as $p)
                        <tr>
                            <td>{{ $p->code }}</td>
                            <td>{{ $p->name }}</td>
                            <td @class(['num', 'ko' => (float) $p->stock_total <= 0])>{{ $qty($p->stock_total) }} {{ $p->unit }}</td>
                            <td class="num">{{ $qty($p->min_stock) }}</td>
                            <td class="num">{{ $qty($p->suggestedOrder()) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>
    @endif

    @if($latePlans !== null)
        <section aria-labelledby="r-plans">
            <h2 id="r-plans">{{ __('kpi.report.late_plans') }} ({{ $latePlans->count() }}) <span class="muted" style="font-weight:400;font-size:12px">· {{ __('kpi.report.now_note') }}</span></h2>
            @if($latePlans->isEmpty())
                <p class="empty">{{ __('kpi.report.late_plans_none') }}</p>
            @else
                <div class="scroll"><table>
                    <thead><tr><th>{{ __('kpi.report.col.plan') }}</th><th>{{ __('kpi.report.col.asset') }}</th><th class="num">{{ __('kpi.report.col.due') }}</th><th class="num">{{ __('kpi.report.col.late') }}</th><th>{{ __('kpi.report.col.work_order') }}</th></tr></thead>
                    <tbody>
                    @foreach($latePlans as $plan)
                        <tr>
                            <td>{{ $plan->title }}</td>
                            <td>{{ $plan->asset->code }} {{ $plan->asset->name }}</td>
                            @if($plan->isTime())
                                <td class="num">{{ $plan->next_due_on?->translatedFormat('j M Y') }}</td>
                                <td class="num ko">{{ $plan->next_due_on ? trans_choice('kpi.report.days_late', (int) $plan->next_due_on->diffInDays(today()), ['n' => (int) $plan->next_due_on->diffInDays(today())]) : '—' }}</td>
                            @else
                                <td class="num" colspan="2">{{ __('kpi.report.meter_due', ['due' => $qty($plan->next_meter_due), 'now' => $qty($plan->meter->current_value ?? 0)]) }}</td>
                            @endif
                            <td>{{ $plan->currentOrder?->number ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>
    @endif

    <footer>{{ __('kpi.report.footer') }}</footer>
</main>
<x-page-actions />
</body>
</html>
