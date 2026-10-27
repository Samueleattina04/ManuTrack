{{-- Menu "Esporta": CSV ed Excel dell'elenco con i filtri applicati (rotta export.*).
     $parts (facoltativo): esportazioni CSV separate, chiave => etichetta (indicatori) --}}
@props(['route', 'params' => [], 'parts' => null])
@php
    $params = array_filter($params, fn ($v) => $v !== '' && $v !== null);
    $xlsx = \App\Support\Export\SpreadsheetWriter::xlsxAvailable();
@endphp
<div x-data="{ open: false, left: false }" {{ $attributes->merge(['class' => 'relative inline-block text-left']) }} @keydown.escape="open = false" @click.outside="open = false">
    <button type="button" class="btn btn-secondary" @click="left = $el.getBoundingClientRect().left < window.innerWidth / 2; open = ! open" :aria-expanded="open.toString()" aria-haspopup="true">
        <x-icon name="download" />{{ __('export.button') }} <x-icon name="chevron-down" class="!w-4 !h-4" />
    </button>
    <div x-cloak x-show="open" x-transition class="absolute z-20 mt-1 w-72 max-w-[calc(100vw-2rem)] card p-1.5 text-left" :class="left ? 'left-0' : 'right-0'" >
        <p class="px-3 py-1.5 text-xs text-muted">{{ __('export.hint') }}</p>
        @if($xlsx)
            <a class="nav-item w-full" href="{{ route($route, ['format' => 'xlsx', ...$params]) }}" @click="open = false" download>
                <x-icon name="file-spreadsheet" />{{ $parts ? __('export.kpi_xlsx') : __('export.xlsx') }}
            </a>
        @endif
        @if($parts)
            @foreach($parts as $key => $label)
                <a class="nav-item w-full" href="{{ route($route, ['format' => 'csv', ...$params, 'parte' => $key]) }}" @click="open = false" download>
                    <x-icon name="file-text" />{{ __('export.kpi_csv', ['part' => $label]) }}
                </a>
            @endforeach
        @else
            <a class="nav-item w-full" href="{{ route($route, ['format' => 'csv', ...$params]) }}" @click="open = false" download>
                <x-icon name="file-text" />{{ __('export.csv') }}
            </a>
        @endif
    </div>
</div>
