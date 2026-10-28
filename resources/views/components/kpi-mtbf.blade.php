{{-- Valore dell'MTBF: con un calendario di lavoro, principale sulle ore di lavoro (docs/KPI.md §2.9)
     e secondario sulle 24 ore; senza calendari, solo sulle 24 ore (§2.3). --}}
@props(['mtbf'])
@php
    $h = fn ($v) => number_format((float) $v, 1, ',', '.');
    $work = $mtbf['work'] ?? null;
    $h24 = $mtbf['insufficient'] ? __('worktime.kpi.h24_insufficient') : __('worktime.kpi.h24_secondary', ['value' => $h($mtbf['value']).' h']);
@endphp
@if($work)
    <p class="text-xs font-semibold text-accent-fg">{{ $work['basis'] === 'lavoro' ? __('worktime.kpi.work_label') : __('worktime.kpi.mixed_label') }}</p>
    @if($mtbf['failures'] === 0)
        <p class="text-lg font-bold text-muted">{{ __('kpi.mtbf.no_failures') }}</p>
    @elseif($work['insufficient'])
        <p class="text-lg font-bold text-muted">{{ __('kpi.insufficient') }}</p>
        <p class="text-xs text-muted">{{ __('worktime.kpi.work_need', ['n' => $mtbf['failures'], 'hours' => $h($work['observed_hours']), 'min' => \App\Support\Kpi\KpiCalculator::MIN_MTBF_FAILURES, 'minh' => \App\Support\Kpi\KpiCalculator::MIN_MTBF_WORK_HOURS]) }}</p>
    @else
        <p class="num font-display text-3xl font-bold">{{ $h($work['value']) }} <span class="text-base font-normal text-muted">h</span></p>
        <p class="text-xs text-muted">{{ __('worktime.kpi.work_on', ['n' => $mtbf['failures'], 'hours' => $h($work['observed_hours'])]) }}</p>
    @endif
    <p class="text-xs text-muted">{{ $h24 }}</p>
@else
    @if($mtbf['insufficient'])
        <p class="text-lg font-bold text-muted">{{ $mtbf['failures'] === 0 && $mtbf['assets'] > 0 ? __('kpi.mtbf.no_failures') : __('kpi.insufficient') }}</p>
        <p class="text-xs text-muted">{{ __('kpi.mtbf.need', ['n' => $mtbf['failures'], 'min' => \App\Support\Kpi\KpiCalculator::MIN_MTBF_FAILURES, 'days' => $h($mtbf['observed_hours'] / 24)]) }}</p>
    @else
        <p class="num font-display text-3xl font-bold">{{ $h($mtbf['value']) }} <span class="text-base font-normal text-muted">h</span></p>
        <p class="text-xs text-muted">{{ __('kpi.mtbf.on', ['n' => $mtbf['failures'], 'assets' => $mtbf['assets'], 'days' => $h($mtbf['value'] / 24)]) }}</p>
    @endif
@endif
