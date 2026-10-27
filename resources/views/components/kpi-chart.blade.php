@props(['id', 'title', 'summary', 'config', 'headers', 'rows', 'height' => 'h-56'])
{{-- Grafico con la sua tabella equivalente: il grafico è un'immagine descritta a parole,
     la tabella (sempre nel DOM) porta gli stessi numeri per chi usa un lettore di schermo o stampa. --}}
<figure class="card flex flex-col gap-3 p-4" aria-labelledby="{{ $id }}-t">
    <figcaption id="{{ $id }}-t" class="text-base font-bold">{{ $title }}</figcaption>
    <div class="relative {{ $height }} print:hidden" wire:ignore wire:key="{{ $id }}-{{ md5(json_encode($config)) }}"
         x-data="kpiChart(@js($config))">
        <canvas x-ref="canvas" role="img" aria-label="{{ $summary }}"></canvas>
    </div>
    <details class="group text-sm print:block" @if(count($rows) === 0) open @endif>
        <summary class="cursor-pointer text-accent-fg underline print:hidden">{{ __('kpi.charts.show_table') }}</summary>
        <div class="mt-2 overflow-x-auto">
            <table class="table">
                <thead><tr>@foreach($headers as $h)<th @class(['text-right' => ! $loop->first])>{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>@foreach($row as $cell)<td @class(['num text-right' => ! $loop->first])>{{ $cell }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($headers) }}" class="py-4 text-center text-muted">{{ __('kpi.no_events') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>
</figure>
