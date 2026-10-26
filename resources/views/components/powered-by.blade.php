@props(['version' => false])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs text-muted']) }}>
    <x-logo variant="mark" :shadow="false" class="h-3.5 w-auto" :label="null" />
    <span>{{ __('app.powered_by') }}</span>@if($version)<span class="font-mono">{{ manutrack_version() }}</span>@endif
</span>
