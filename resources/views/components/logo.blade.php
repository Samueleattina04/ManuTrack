@php($l = $layout())
<svg {{ $attributes->merge(['class' => 'mt-logo']) }} viewBox="{{ $viewBox() }}" xmlns="http://www.w3.org/2000/svg"
     @if($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" focusable="false" @endif>
    <defs>
        <linearGradient id="{{ $uid }}s" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#000" stop-opacity=".9"/><stop offset="1" stop-color="#000" stop-opacity="0"/></linearGradient>
        <linearGradient id="{{ $uid }}a" x1="0" y1="0" x2=".35" y2=".75"><stop offset="0" stop-color="#000" stop-opacity=".5"/><stop offset=".55" stop-color="#000" stop-opacity="0"/></linearGradient>
        <linearGradient id="{{ $uid }}h" x1="1" y1=".2" x2=".2" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".16"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/></linearGradient>
        <linearGradient id="{{ $uid }}b" x1="1" y1="0" x2=".2" y2=".9"><stop offset="0" stop-color="#fff" stop-opacity=".09"/><stop offset=".7" stop-color="#fff" stop-opacity="0"/></linearGradient>
    </defs>
    <g @if($l['mark']) transform="{{ $l['mark'] }}" @endif>
        @if($shadow)<polygon class="mt-logo-shadow" points="{{ $shadowPoints() }}" fill="url(#{{ $uid }}s)"/>@endif
        <polygon class="mt-logo-light" points="{{ $light() }}"/>
        <polygon class="mt-logo-shade" points="{{ $light() }}" fill="url(#{{ $uid }}a)"/>
        <polygon class="mt-logo-gloss" points="{{ $light() }}" fill="url(#{{ $uid }}h)"/>
        <polygon class="mt-logo-dark" points="{{ $dark() }}"/>
        <polygon class="mt-logo-gloss" points="{{ $dark() }}" fill="url(#{{ $uid }}b)"/>
    </g>
    @if($l['word'] !== null)
        @if($wordmark())
            <path class="mt-logo-text" transform="{{ $l['word'] }}" d="{{ $wordmark() }}"/>
        @else
            <text class="mt-logo-text" transform="{{ $l['word'] }}" x="-184" y="584" textLength="780" lengthAdjust="spacingAndGlyphs" font-size="150" font-weight="700" font-family="'Open Sans', Arial, sans-serif">ManuTrack</text>
        @endif
    @endif
</svg>
