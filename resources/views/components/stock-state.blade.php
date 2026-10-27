@props(['part'])
{{-- Stato della scorta: sempre icona e testo --}}
@switch($part->stockState())
    @case('esaurito')<x-status tone="ko" icon="x-circle" {{ $attributes }}>{{ __('inventory.state.esaurito') }}</x-status>@break
    @case('sotto')<x-status tone="warn" icon="alert-triangle" {{ $attributes }}>{{ __('inventory.state.sotto') }}</x-status>@break
    @default<x-status tone="ok" icon="check-circle" {{ $attributes }}>{{ __('inventory.state.ok') }}</x-status>
@endswitch
