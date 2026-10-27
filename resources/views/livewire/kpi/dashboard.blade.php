@php
    $h = fn ($v) => number_format((float) $v, 1, ',', '.');
    $eur = fn ($v) => '€ '.number_format((float) $v, 2, ',', '.');
    $pct = fn ($v) => number_format((float) $v, 1, ',', '.').'%';
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    {{-- Periodo e filtri --}}
    <section class="card flex flex-col gap-4 p-4 sm:p-5" aria-labelledby="k-period">
        <h2 id="k-period" class="sr-only">{{ __('kpi.period') }}</h2>
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="{{ __('kpi.period') }}">
            @foreach($presets as $p)
                <button type="button" wire:click="choose('{{ $p }}')" @class(['status-filter', 'is-active' => $preset === $p]) aria-pressed="{{ $preset === $p ? 'true' : 'false' }}">{{ __('kpi.presets.'.$p) }}</button>
            @endforeach
            <button type="button" wire:click="$set('preset', 'scelta')" @class(['status-filter', 'is-active' => $preset === 'scelta']) aria-pressed="{{ $preset === 'scelta' ? 'true' : 'false' }}">{{ __('kpi.presets.scelta') }}</button>
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-form.input name="from" type="date" :label="__('kpi.from')" wire:model.live.debounce.500ms="from" wire:change="$set('preset', 'scelta')" />
            <x-form.input name="to" type="date" :label="__('kpi.to')" wire:model.live.debounce.500ms="to" wire:change="$set('preset', 'scelta')" />
            <div class="field">
                <label for="k-loc" class="field-label">{{ __('assets.field.location_id') }}</label>
                <select id="k-loc" wire:model.live="location" class="input"><option value="">{{ __('kpi.all') }}</option>@foreach($locations as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
            </div>
            <div class="field">
                <label for="k-cat" class="field-label">{{ __('assets.field.asset_category_id') }}</label>
                <select id="k-cat" wire:model.live="category" class="input"><option value="">{{ __('kpi.all') }}</option>@foreach($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
            </div>
        </div>
        @if($error)<div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ $error }}</span></div>@endif
<div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <p class="min-w-0 text-sm text-muted sm:flex-1">{{ __('kpi.period_hint', ['from' => $period->start->translatedFormat('j M Y H:i'), 'to' => $period->end->translatedFormat('j M Y H:i'), 'tz' => config('app.timezone')]) }} · <a href="#k-notes" class="underline">{{ __('kpi.how') }}</a></p>
            <div class="flex flex-wrap gap-2">
            <a href="{{ route('kpi.report') }}" class="btn btn-secondary" target="_blank" rel="noopener"><x-icon name="printer" />{{ __('kpi.report.button') }}</a>
            @can('reports.export')
                <x-export-menu route="export.kpi" :params="['periodo' => $preset, 'dal' => $from, 'al' => $to, 'ubicazione' => $location, 'categoria' => $category]" :parts="array_filter(__('export.kpi_parts'), fn ($k) => $k !== 'costi' || $canCosts, ARRAY_FILTER_USE_KEY)" />
            @endcan
            </div>
        </div>
    </section>

    {{-- Indicatori principali --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- MTTR --}}
        <section class="card flex flex-col gap-1 p-4" aria-labelledby="k-mttr">
            <h2 id="k-mttr" class="text-sm font-semibold text-fg-2">{{ __('kpi.mttr.title') }}</h2>
            @if($mttr['insufficient'])
                <p class="text-lg font-bold text-muted">{{ __('kpi.insufficient') }}</p>
                <p class="text-xs text-muted">{{ trans_choice('kpi.mttr.need', $mttr['n'], ['n' => $mttr['n'], 'min' => \App\Support\Kpi\KpiCalculator::MIN_MTTR_EVENTS]) }}</p>
            @else
                <p class="num font-display text-3xl font-bold">{{ $h($mttr['value']) }} <span class="text-base font-normal text-muted">h</span></p>
                <p class="text-xs text-muted">{{ trans_choice('kpi.mttr.on', $mttr['n'], ['n' => $mttr['n']]) }}</p>
            @endif
        </section>
        {{-- MTBF --}}
        <section class="card flex flex-col gap-1 p-4" aria-labelledby="k-mtbf">
            <h2 id="k-mtbf" class="text-sm font-semibold text-fg-2">{{ __('kpi.mtbf.title') }}</h2>
            @if($mtbf['insufficient'])
                <p class="text-lg font-bold text-muted">{{ $mtbf['failures'] === 0 && $mtbf['assets'] > 0 ? __('kpi.mtbf.no_failures') : __('kpi.insufficient') }}</p>
                <p class="text-xs text-muted">{{ __('kpi.mtbf.need', ['n' => $mtbf['failures'], 'min' => \App\Support\Kpi\KpiCalculator::MIN_MTBF_FAILURES, 'days' => $h($mtbf['observed_hours'] / 24)]) }}</p>
            @else
                <p class="num font-display text-3xl font-bold">{{ $h($mtbf['value']) }} <span class="text-base font-normal text-muted">h</span></p>
                <p class="text-xs text-muted">{{ __('kpi.mtbf.on', ['n' => $mtbf['failures'], 'assets' => $mtbf['assets'], 'days' => $h($mtbf['value'] / 24)]) }}</p>
            @endif
        </section>
        {{-- Fermi --}}
        <section class="card flex flex-col gap-1 p-4" aria-labelledby="k-down">
            <h2 id="k-down" class="text-sm font-semibold text-fg-2">{{ __('kpi.downtime.title') }}</h2>
            <p class="num font-display text-3xl font-bold">{{ $downtime['count'] }} <span class="text-base font-normal text-muted">· {{ $h($downtime['hours']) }} h</span></p>
            <p class="text-xs text-muted">{{ __('kpi.downtime.split', ['failures' => $downtime['failure_count'], 'fh' => $h($downtime['failure_hours']), 'planned' => $downtime['planned_count'], 'ph' => $h($downtime['planned_hours'])]) }}@if($downtime['open']) · <span class="font-semibold text-ko">{{ trans_choice('kpi.downtime.open', $downtime['open'], ['n' => $downtime['open']]) }}</span>@endif</p>
        </section>
        {{-- Arretrato --}}
        <section class="card flex flex-col gap-1 p-4" aria-labelledby="k-back">
            <h2 id="k-back" class="text-sm font-semibold text-fg-2">{{ __('kpi.backlog.title') }}</h2>
            <p class="num font-display text-3xl font-bold">{{ $backlog['open'] }} @if($backlog['overdue'])<span class="text-base font-semibold text-ko">· {{ trans_choice('kpi.backlog.overdue', $backlog['overdue'], ['n' => $backlog['overdue']]) }}</span>@endif</p>
            <p class="text-xs text-muted">{{ $backlog['avg_age_days'] === null ? __('kpi.backlog.none') : __('kpi.backlog.age', ['days' => $h($backlog['avg_age_days'])]) }} · {{ __('kpi.backlog.now') }}</p>
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Preventivo / correttivo --}}
        <section class="card flex flex-col gap-3 p-4" aria-labelledby="k-mix">
            <h2 id="k-mix" class="text-base font-bold">{{ __('kpi.mix.title') }}</h2>
            @if($mix['insufficient'])
                <p class="text-sm text-muted"><strong>{{ __('kpi.insufficient') }}</strong> · {{ __('kpi.mix.need', ['n' => $mix['preventive'] + $mix['corrective'], 'min' => \App\Support\Kpi\KpiCalculator::MIN_MIX_ORDERS]) }}</p>
            @else
                <div class="flex h-4 overflow-hidden rounded-full bg-surface-2" role="img" aria-label="{{ __('kpi.mix.aria', ['p' => $pct($mix['preventive_pct']), 'c' => $pct($mix['corrective_pct'])]) }}">
                    <div class="bg-[var(--mt-ok)]" style="width: {{ $mix['preventive_pct'] }}%"></div>
                    <div class="bg-[var(--mt-warn)]" style="width: {{ $mix['corrective_pct'] }}%"></div>
                </div>
                <dl class="grid grid-cols-3 gap-2 text-sm">
                    <div><dt class="flex items-center gap-1.5 text-muted"><span class="h-2.5 w-2.5 rounded-full bg-[var(--mt-ok)]"></span>{{ __('kpi.mix.preventive') }}</dt><dd class="num text-lg font-bold">{{ $pct($mix['preventive_pct']) }} <span class="text-sm font-normal text-muted">({{ $mix['preventive'] }})</span></dd></div>
                    <div><dt class="flex items-center gap-1.5 text-muted"><span class="h-2.5 w-2.5 rounded-full bg-[var(--mt-warn)]"></span>{{ __('kpi.mix.corrective') }}</dt><dd class="num text-lg font-bold">{{ $pct($mix['corrective_pct']) }} <span class="text-sm font-normal text-muted">({{ $mix['corrective'] }})</span></dd></div>
                    <div><dt class="text-muted">{{ __('kpi.mix.improvement') }}</dt><dd class="num text-lg font-bold">{{ $mix['improvement'] }}</dd></div>
                </dl>
            @endif
        </section>
        {{-- Rispetto dei piani --}}
        <section class="card flex flex-col gap-3 p-4" aria-labelledby="k-comp">
            <h2 id="k-comp" class="text-base font-bold">{{ __('preventive.compliance.title') }}</h2>
            @if($compliance['insufficient'])
                <p class="text-sm text-muted"><strong>{{ __('kpi.insufficient') }}</strong> · {{ __('kpi.compliance.need', ['n' => $compliance['total'], 'min' => \App\Support\Kpi\KpiCalculator::MIN_COMPLIANCE]) }}</p>
            @else
                <p class="num font-display text-3xl font-bold">{{ $pct($compliance['rate']) }}</p>
            @endif
            <p class="text-sm text-fg-2">{{ __('preventive.compliance.on_time') }} {{ $compliance['on_time'] }} · {{ __('preventive.compliance.late') }} {{ $compliance['late'] }} · {{ __('preventive.compliance.skipped') }} {{ $compliance['skipped'] }} · {{ __('preventive.compliance.overdue') }} {{ $compliance['overdue'] }}</p>
        </section>
    </div>

    {{-- Andamento nel tempo --}}
    @php
        $b = $series['buckets'];
        $labels = array_column($b, 'label');
        $unitLabel = __('kpi.charts.unit.'.$series['unit']);
        $empty = __('kpi.insufficient_short');
        $costRows = $canCosts && $costs !== null ? array_slice(array_values(array_filter($costs['rows'], fn ($r) => $r['asset_id'] !== null && $r['total'] > 0)), 0, 10) : [];
        $costNames = \App\Models\Asset::query()->whereIn('id', array_column($costRows, 'asset_id'))->pluck('code', 'id');
    @endphp
    <div class="grid gap-4 lg:grid-cols-2">
        <x-kpi-chart id="ch-down" :title="__('kpi.charts.downtime', ['unit' => $unitLabel])"
            :summary="__('kpi.charts.downtime_summary', ['count' => $downtime['count'], 'hours' => $h($downtime['hours']), 'n' => count($b)])"
            :config="['labels' => $labels, 'unit' => 'h', 'empty' => $empty, 'datasets' => [['label' => __('kpi.hours'), 'data' => array_column($b, 'downtime_hours'), 'color' => 'warn']]]"
            :headers="[__('kpi.charts.period'), __('kpi.downtime.title'), __('kpi.hours')]"
            :rows="array_map(fn ($r) => [$r['label'], $r['downtime_count'], $h($r['downtime_hours'])], $b)" />
        <x-kpi-chart id="ch-mttr" :title="__('kpi.charts.mttr', ['unit' => $unitLabel])"
            :summary="__('kpi.charts.mttr_summary', ['n' => count(array_filter(array_column($b, 'mttr'), fn ($v) => $v !== null)), 'total' => count($b)])"
            :config="['type' => 'line', 'labels' => $labels, 'unit' => 'h', 'empty' => $empty, 'datasets' => [['label' => 'MTTR', 'type' => 'line', 'data' => array_column($b, 'mttr'), 'color' => 'accent']]]"
            :headers="[__('kpi.charts.period'), 'MTTR (h)', __('kpi.charts.events')]"
            :rows="array_map(fn ($r) => [$r['label'], $r['mttr'] === null ? $empty : $h($r['mttr']), $r['mttr_n']], $b)" />
        <x-kpi-chart id="ch-mix" :title="__('kpi.charts.mix', ['unit' => $unitLabel])"
            :summary="__('kpi.charts.mix_summary', ['p' => $mix['preventive'], 'c' => $mix['corrective']])"
            :config="['labels' => $labels, 'stacked' => true, 'empty' => $empty, 'datasets' => [['label' => __('kpi.mix.preventive'), 'data' => array_column($b, 'preventive'), 'color' => 'ok'], ['label' => __('kpi.mix.corrective'), 'data' => array_column($b, 'corrective'), 'color' => 'warn']]]"
            :headers="[__('kpi.charts.period'), __('kpi.mix.preventive'), __('kpi.mix.corrective')]"
            :rows="array_map(fn ($r) => [$r['label'], $r['preventive'], $r['corrective']], $b)" />
        @if($canCosts)
            <x-kpi-chart id="ch-cost" :title="__('kpi.charts.costs')" height="h-72"
                :summary="__('kpi.charts.costs_summary', ['n' => count($costRows), 'total' => $eur($costs['total']['total'] ?? 0)])"
                :config="['labels' => array_map(fn ($r) => $costNames[$r['asset_id']] ?? '—', $costRows), 'horizontal' => true, 'unit' => '€', 'empty' => $empty, 'datasets' => [['label' => __('kpi.costs.total'), 'data' => array_map(fn ($r) => round($r['total'], 2), $costRows), 'color' => 'accent']]]"
                :headers="[__('assets.field.code'), __('kpi.costs.total')]"
                :rows="array_map(fn ($r) => [$costNames[$r['asset_id']] ?? '—', $eur($r['total'])], $costRows)" />
        @endif
    </div>

    {{-- Per macchina --}}
    <section class="card overflow-hidden" aria-labelledby="k-assets">
        <h2 id="k-assets" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('kpi.by_asset') }}</h2>
        <div class="overflow-x-auto">
            <table class="table min-w-[720px]">
                <thead><tr>
                    <th>{{ __('assets.field.code') }}</th>
                    <th class="text-right">{{ __('kpi.downtime.title') }}</th>
                    <th class="text-right">{{ __('kpi.hours') }}</th>
                    <th class="text-right">{{ __('kpi.mttr.short') }}</th>
                    <th class="text-right">{{ __('kpi.mtbf.short') }}</th>
                    @if($canCosts)<th class="text-right">{{ __('kpi.costs.title') }}</th>@endif
                </tr></thead>
                <tbody>
                    @forelse($assets as $r)
                        <tr wire:key="ka-{{ $r['asset']->id }}">
                            <td><a href="{{ route('assets.show', $r['asset']) }}" class="hover:underline"><span class="font-mono">{{ $r['asset']->code }}</span> <span class="text-fg-2">{{ $r['asset']->name }}</span></a></td>
                            <td class="num text-right">{{ $r['downtime']['count'] }}</td>
                            <td class="num text-right">{{ $h($r['downtime']['hours']) }}</td>
                            <td class="num text-right">@if($r['mttr']['insufficient'])<span class="text-xs text-muted" title="{{ trans_choice('kpi.mttr.need', $r['mttr']['n'], ['n' => $r['mttr']['n'], 'min' => \App\Support\Kpi\KpiCalculator::MIN_MTTR_EVENTS]) }}">{{ __('kpi.insufficient_short') }}</span>@else{{ $h($r['mttr']['value']) }} h @endif</td>
                            <td class="num text-right">@if($r['mtbf'] === null)<span class="text-xs text-muted">{{ __('kpi.retired') }}</span>@elseif($r['mtbf']['insufficient'])<span class="text-xs text-muted">{{ __('kpi.insufficient_short') }}</span>@else{{ $h($r['mtbf']['value']) }} h @endif</td>
                            @if($canCosts)<td class="num text-right">{{ $r['cost'] ? $eur($r['cost']['total']) : '—' }}</td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-muted">{{ __('kpi.no_events') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Costi --}}
    @if($canCosts && $costs !== null)
        <section class="card overflow-hidden" aria-labelledby="k-costs">
            <div class="flex flex-wrap items-baseline gap-2 border-b border-line-soft px-4 py-3">
                <h2 id="k-costs" class="flex-1 text-base font-bold">{{ __('kpi.costs.title') }}</h2>
                <span class="num text-lg font-bold">{{ $eur($costs['total']['total']) }}</span>
                <span class="text-sm text-muted">{{ trans_choice('kpi.costs.orders', $costs['total']['orders'], ['n' => $costs['total']['orders']]) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table min-w-[640px]">
                    <thead><tr><th>{{ __('assets.field.code') }}</th><th class="text-right">{{ __('kpi.costs.labor') }}</th><th class="text-right">{{ __('kpi.costs.parts') }}</th><th class="text-right">{{ __('kpi.costs.external') }}</th><th class="text-right">{{ __('kpi.costs.total') }}</th></tr></thead>
                    <tbody>
                        @php $assetNames = \App\Models\Asset::query()->whereIn('id', collect($costs['rows'])->pluck('asset_id')->filter())->pluck('code', 'id'); @endphp
                        @forelse($costs['rows'] as $row)
                            <tr>
                                <td>@if($row['asset_id'])<span class="font-mono">{{ $assetNames[$row['asset_id']] ?? '—' }}</span>@else<span class="text-muted">{{ __('kpi.costs.no_asset') }}</span>@endif <span class="text-xs text-muted">· {{ trans_choice('kpi.costs.orders', $row['orders'], ['n' => $row['orders']]) }}</span></td>
                                <td class="num text-right">{{ $eur($row['labor']) }}</td><td class="num text-right">{{ $eur($row['parts']) }}</td><td class="num text-right">{{ $eur($row['external']) }}</td><td class="num text-right font-semibold">{{ $eur($row['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-muted">{{ __('kpi.costs.none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Come si calcolano --}}
    <section id="k-notes" class="card p-4 text-sm text-fg-2" aria-labelledby="k-notes-title">
        <h2 id="k-notes-title" class="mb-2 text-base font-bold text-fg">{{ __('kpi.how') }}</h2>
        <ul class="flex list-disc flex-col gap-1 pl-5">
            @foreach(__('kpi.notes') as $note)<li>{{ $note }}</li>@endforeach
        </ul>
    </section>
</div>
