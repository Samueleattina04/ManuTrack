@props(['priority'])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-[13px] font-semibold text-fg-2']) }}
      style="--c: var(--mt-{{ $priority->tone() }})">
    <span class="inline-flex gap-0.5" aria-hidden="true">
        @for($i = 1; $i <= 4; $i++)
            <span class="h-3 w-1 rounded-sm" style="background: {{ $i <= $priority->level() ? 'var(--c)' : 'var(--mt-line)' }}"></span>
        @endfor
    </span>
    <span>{{ $priority->label() }}</span>
</span>
