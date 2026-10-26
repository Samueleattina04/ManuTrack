@props(['name', 'label' => null])
<svg {{ $attributes->merge(['class' => 'mt-icon']) }} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
     @if($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" focusable="false" @endif>{!! \App\Support\Icons::inner($name) !!}</svg>
