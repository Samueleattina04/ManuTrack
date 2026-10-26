@props(['item'])
<a href="{{ $item['url'] ?? '#' }}" class="nav-item"
   @if($item['active']) aria-current="page" @endif
   @if($item['disabled']) aria-disabled="true" tabindex="-1" @endif
   @if($item['shortcut']) data-shortcut="{{ $item['shortcut'] }}" @endif>
    <x-icon :name="$item['icon']" />
    <span class="flex-1 truncate">{{ $item['label'] }}</span>
    @if($item['disabled'])
        <span class="text-[11px] text-muted">{{ __('app.soon') }}</span>
    @elseif($item['shortcut'])
        <span class="kbd uppercase">{{ $item['shortcut'] }}</span>
    @endif
</a>
