@props(['item'])
<a href="{{ $item['url'] ?? '#' }}" class="nav-item"
   @if($item['active']) aria-current="page" @endif
   @if($item['disabled']) aria-disabled="true" tabindex="-1" @endif
   @if($item['shortcut']) data-shortcut="{{ $item['shortcut'] }}" @endif>
    <x-icon :name="$item['icon']" />
    <span class="flex-1 truncate">{{ $item['label'] }}</span>
    @if(($item['badge'] ?? 0) > 0)
        <span class="num min-w-[1.5rem] rounded-full bg-[var(--mt-ko)] px-1.5 text-center text-xs font-bold text-white" aria-label="{{ trans_choice('notifications.unread', $item['badge'], ['count' => $item['badge']]) }}">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
    @elseif($item['disabled'])
        <span class="text-[11px] text-muted">{{ __('app.soon') }}</span>
    @elseif($item['shortcut'])
        <span class="kbd uppercase">{{ $item['shortcut'] }}</span>
    @endif
</a>
