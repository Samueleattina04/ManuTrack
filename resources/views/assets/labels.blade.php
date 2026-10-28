@php
    $perPage = $spec['cols'] * $spec['rows'];
    $cells = collect(array_fill(0, $skip, null))->concat($assets);
    $pages = $cells->chunk($perPage);
    $small = $format === 'piccole';
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('assets.labels.title') }} · {{ $company }}</title>
    <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        :root { color-scheme: light; --w: {{ $spec['w'] }}mm; --h: {{ $spec['h'] }}mm; --qr: {{ $spec['qr'] }}mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #e9ecf1; color: #111; font-family: "IBM Plex Sans", system-ui, -apple-system, "Segoe UI", sans-serif; }
        .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: center; padding: 12px 16px; background: #fff; border-bottom: 1px solid #d5dae1; font-size: 14px; }
        .bar strong { font-size: 16px; }
        .bar label { display: inline-flex; gap: 6px; align-items: center; }
        .bar select, .bar input, .bar button, .bar a { font: inherit; min-height: 40px; border-radius: 10px; border: 1px solid #c6ccd5; padding: 0 12px; background: #fff; color: #111; text-decoration: none; display: inline-flex; align-items: center; }
        .bar button.primary { background: #1c2430; color: #fff; border-color: #1c2430; font-weight: 600; cursor: pointer; }
        .bar .hint { color: #5b6472; flex-basis: 100%; font-size: 13px; }
        .sheet { width: 210mm; height: 297mm; margin: 16px auto; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); display: grid;
                 grid-template-columns: repeat({{ $spec['cols'] }}, var(--w)); grid-template-rows: repeat({{ $spec['rows'] }}, var(--h));
                 justify-content: center; align-content: center; page-break-after: always; break-after: page; overflow: hidden; }
        .label { width: var(--w); height: var(--h); padding: {{ $small ? '2.5mm' : '5mm' }}; display: flex; gap: {{ $small ? '2.5mm' : '4mm' }}; align-items: center;
                 outline: 1px dashed #d5dae1; outline-offset: -1px; overflow: hidden; {{ $format === 'grandi' ? 'flex-direction: column; justify-content: center; text-align: center;' : '' }} }
        .label .qr { width: var(--qr); height: var(--qr); flex: none; }
        .label .qr svg { width: 100%; height: 100%; display: block; }
        .label .txt { min-width: 0; display: flex; flex-direction: column; gap: {{ $small ? '.6mm' : '1.5mm' }}; }
        .code { font-family: "IBM Plex Mono", ui-monospace, monospace; font-weight: 700; font-size: {{ $small ? '11pt' : ($format === 'medie' ? '16pt' : '26pt') }}; line-height: 1.1; word-break: break-all; }
        .name { font-weight: 600; font-size: {{ $small ? '7.5pt' : ($format === 'medie' ? '11pt' : '15pt') }}; line-height: 1.2; display: -webkit-box; -webkit-line-clamp: {{ $small ? 2 : 3 }}; -webkit-box-orient: vertical; overflow: hidden; }
        .loc, .company, .scan { font-size: {{ $small ? '6pt' : ($format === 'medie' ? '8pt' : '11pt') }}; color: #444; line-height: 1.25; }
        .company { font-weight: 600; color: #111; }
        .empty { outline-color: #eef0f3; }
        @media print {
            @page { size: A4 portrait; margin: 0; }
            html, body { background: #fff; }
            .bar { display: none; }
            .sheet { margin: 0; box-shadow: none; }
            .label { outline: none; }
        }
    </style>
</head>
<body>
    <form class="bar" method="get" action="{{ route('assets.labels') }}">
        <strong>{{ trans_choice('assets.labels.count', $assets->count(), ['count' => $assets->count()]) }}</strong>
        <input type="hidden" name="ids" value="{{ $ids }}">
        <label>{{ __('assets.labels.format') }}
            <select name="formato" data-submit-on-change>
                @foreach($formats as $key => $f)
                    <option value="{{ $key }}" @selected($key === $format)>{{ __('assets.labels.formats.'.$key, ['n' => $f['cols'] * $f['rows'], 'w' => str_replace('.', ',', (string) $f['w']), 'h' => str_replace('.', ',', (string) $f['h'])]) }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ __('assets.labels.skip') }}
            <input type="number" name="salta" min="0" max="{{ $perPage - 1 }}" value="{{ $skip }}" style="width: 5.5rem" data-submit-on-change>
        </label>
        <button type="button" class="primary" data-print>{{ __('assets.labels.print') }}</button>
        <a href="{{ route('assets.index') }}">{{ __('assets.title') }}</a>
        <span class="hint">{{ __('assets.labels.hint') }}</span>
    </form>

    @forelse($pages as $page)
        <div class="sheet">
            @foreach($page as $asset)
                @if($asset === null)
                    <div class="label empty" aria-hidden="true"></div>
                @else
                    <div class="label">
                        <div class="qr">{!! $qr[$asset->id] !!}</div>
                        <div class="txt">
                            <div class="code">{{ $asset->code }}</div>
                            <div class="name">{{ $asset->name }}</div>
                            @if(! $small && $asset->location)<div class="loc">{{ $asset->location->fullName() }}</div>@endif
                            <div class="company">{{ $company }}</div>
                            @unless($small)<div class="scan">{{ __('assets.labels.scan') }}</div>@endunless
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @empty
        <p style="padding: 24px; text-align: center">{{ __('assets.labels.none') }}</p>
    @endforelse
<x-page-actions />
</body>
</html>
