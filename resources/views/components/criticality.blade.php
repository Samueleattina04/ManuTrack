@props(['value'])
{{-- Criticità A/B/C: lettera in un quadratino colorato + testo per gli screen reader --}}
<span {{ $attributes->merge(['class' => 'inline-grid h-6 min-w-6 place-items-center rounded-md border px-1 font-mono text-xs font-bold']) }}
      style="color: var(--mt-{{ $value->tone() }}); border-color: color-mix(in oklab, var(--mt-{{ $value->tone() }}) 45%, transparent); background: color-mix(in oklab, var(--mt-{{ $value->tone() }}) 12%, transparent)"
      title="{{ __('assets.field.criticality') }}: {{ $value->label() }}">
    <span aria-hidden="true">{{ $value->value }}</span><span class="sr-only">{{ __('assets.field.criticality') }} {{ $value->label() }}</span>
</span>
