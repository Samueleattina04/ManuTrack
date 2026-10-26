<div x-data="theme" {{ $attributes->merge(['class' => 'inline-flex rounded-full border border-line bg-bg-2 p-0.5']) }} role="group" aria-label="{{ __('app.theme.label') }}">
    @foreach(['dark' => 'moon', 'light' => 'sun', 'system' => 'monitor'] as $mode => $icon)
        <button type="button" class="grid h-9 w-9 place-items-center rounded-full text-muted transition-colors"
                :class="mode === '{{ $mode }}' && 'bg-surface-2 text-fg shadow-card'"
                :aria-pressed="(mode === '{{ $mode }}').toString()"
                @click="set('{{ $mode }}')" title="{{ __('app.theme.'.$mode) }}">
            <x-icon :name="$icon" class="!w-4 !h-4" />
            <span class="sr-only">{{ __('app.theme.'.$mode) }}</span>
        </button>
    @endforeach
</div>
