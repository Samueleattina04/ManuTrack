@props(['state'])
@switch($state)
    @case('scaduto')<x-status tone="ko" icon="alert-triangle" {{ $attributes }}>{{ __('preventive.state.scaduto') }}</x-status>@break
    @case('in_scadenza')<x-status tone="warn" icon="clock" {{ $attributes }}>{{ __('preventive.state.in_scadenza') }}</x-status>@break
    @case('fermo')<x-status tone="stop" icon="pause-circle" {{ $attributes }}>{{ __('preventive.state.fermo') }}</x-status>@break
    @default<x-status tone="ok" icon="check-circle" {{ $attributes }}>{{ __('preventive.state.ok') }}</x-status>
@endswitch
