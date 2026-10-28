{{-- Pianta in 2D dall'alto (SVG, unità in metri): vista di riserva del 3D e base dell'editor.
     $data come App\Support\Plant\PlantData::for(); $editable aggiunge trascinamento e tastiera. --}}
@props(['data', 'editable' => false, 'selected' => null])
@php
    $W = $data['layout']['width'];
    $D = $data['layout']['depth'];
    $step = max($W, $D) > 120 ? 5 : 1;
    $glyph = ['ok' => '✓', 'ko' => '!', 'mnt' => '⚙', 'stop' => '■'];
    $fs = function (array $i, string $text) {
        $byBox = min($i['w'], $i['d']) * 0.36;
        $byWidth = $i['w'] * 0.9 / max(1, mb_strlen($text) * 0.62);
        return round(max(0.18, min(0.7, $byBox, $byWidth)), 2);
    };
@endphp
<svg {{ $attributes->merge(['class' => 'plant-svg']) }} viewBox="-0.5 -0.5 {{ $W + 1 }} {{ $D + 1 }}" preserveAspectRatio="xMidYMid meet"
     role="group" aria-label="{{ __('plant.title') }}: {{ $data['layout']['name'] }}, {{ __('plant.size', ['w' => $W, 'd' => $D]) }}" x-ref="svg">
    <rect class="plant-floor" x="0" y="0" width="{{ $W }}" height="{{ $D }}" rx="0.1" />
    <g aria-hidden="true">
        @for($x = $step; $x < $W; $x += $step)
            <line class="{{ $x % 5 === 0 ? 'plant-grid-5' : 'plant-grid' }}" x1="{{ $x }}" y1="0" x2="{{ $x }}" y2="{{ $D }}" />
        @endfor
        @for($z = $step; $z < $D; $z += $step)
            <line class="{{ $z % 5 === 0 ? 'plant-grid-5' : 'plant-grid' }}" x1="0" y1="{{ $z }}" x2="{{ $W }}" y2="{{ $z }}" />
        @endfor
    </g>
    @foreach($data['zones'] as $z)
        <g class="plant-zone" transform="translate({{ $z['x'] }} {{ $z['z'] }}) rotate({{ $z['rotation'] }})"
           @if($editable) data-item="{{ $z['id'] }}" data-x="{{ $z['x'] }}" data-z="{{ $z['z'] }}" tabindex="0" role="button" aria-label="{{ __('plant.editor.item_label', ['name' => $z['label'], 'x' => $z['x'], 'z' => $z['z']]) }}"
              @class(['is-selected' => $selected === $z['id']]) @pointerdown="start($event)" @keydown="key($event)" @endif>
            <rect x="{{ -$z['w'] / 2 }}" y="{{ -$z['d'] / 2 }}" width="{{ $z['w'] }}" height="{{ $z['d'] }}" rx="0.15" />
            <text x="{{ -$z['w'] / 2 + 0.2 }}" y="{{ $z['d'] / 2 - 0.2 }}" dominant-baseline="auto" font-size="{{ round(max(0.3, min(0.8, $z['d'] * 0.2)), 2) }}">{{ $z['label'] }}</text>
        </g>
    @endforeach
    @foreach($data['items'] as $i)
        @php($text = $glyph[$i['tone']].' '.$i['code'])
        <g class="plant-item tone-{{ $i['tone'] }} @if($selected === $i['id']) is-selected @endif" data-plant-asset="{{ $i['asset_id'] }}" data-item="{{ $i['id'] }}" data-x="{{ $i['x'] }}" data-z="{{ $i['z'] }}"
           transform="translate({{ $i['x'] }} {{ $i['z'] }}) rotate({{ $i['rotation'] }})" tabindex="0" role="button"
           @if($editable)
               aria-label="{{ __('plant.editor.item_label', ['name' => $i['code'].' '.$i['name'], 'x' => $i['x'], 'z' => $i['z']]) }}"
               @pointerdown="start($event)" @keydown="key($event)"
           @else
               aria-label="{{ $i['code'] }} {{ $i['name'] }}: {{ $i['status_label'] }}"
               :data-selected="selectedId === {{ $i['id'] }}"
               @click="select({{ $i['id'] }})" @keydown.enter.prevent="select({{ $i['id'] }})" @keydown.space.prevent="select({{ $i['id'] }})"
           @endif>
            <title>{{ $i['code'] }} · {{ $i['name'] }} · {{ $i['status_label'] }}</title>
            <rect x="{{ -$i['w'] / 2 }}" y="{{ -$i['d'] / 2 }}" width="{{ $i['w'] }}" height="{{ $i['d'] }}" rx="0.12" />
            <text x="0" y="0" text-anchor="middle" dominant-baseline="central" font-size="{{ $fs($i, $text) }}" transform="rotate({{ -$i['rotation'] }})">{{ $text }}</text>
        </g>
    @endforeach
</svg>
