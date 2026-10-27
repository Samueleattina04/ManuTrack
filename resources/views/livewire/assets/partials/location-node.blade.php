{{-- Un nodo dell'albero delle ubicazioni (si richiama da solo per i figli) --}}
@php $n = (int) ($counts[$loc->id] ?? 0); @endphp
<li wire:key="loc-{{ $loc->id }}">
    <div class="flex items-center gap-2 rounded-[10px] px-2 py-1.5 hover:bg-surface-2">
        <x-icon :name="match($loc->type->value) { 'sito' => 'factory', 'area' => 'layers', default => 'arrow-right' }" class="!w-4 !h-4 shrink-0 text-muted" />
        <div class="min-w-0 flex-1">
            <span class="font-medium">{{ $loc->name }}</span>
            @if($loc->code)<span class="ml-1 font-mono text-xs text-muted">{{ $loc->code }}</span>@endif
            <span class="ml-1 text-xs text-muted">{{ $loc->type->label() }}</span>
        </div>
        <a href="{{ route('assets.index', ['ubicazione' => $loc->id]) }}" class="btn btn-ghost btn-sm num" title="{{ __('assets.locations.show_assets') }}">{{ trans_choice('assets.locations.assets_count', $n, ['count' => $n]) }}</a>
        @if($canManage)
            @if($loc->type->child())
                <button type="button" class="btn btn-ghost btn-sm btn-icon" wire:click="create({{ $loc->id }})" aria-label="{{ __('assets.locations.add_child', ['type' => $loc->type->child()->label(), 'name' => $loc->name]) }}" title="{{ __('assets.locations.add_child', ['type' => $loc->type->child()->label(), 'name' => $loc->name]) }}"><x-icon name="plus" class="!w-4 !h-4" /></button>
            @endif
            <button type="button" class="btn btn-ghost btn-sm btn-icon" wire:click="edit({{ $loc->id }})" aria-label="{{ __('app.edit') }} {{ $loc->name }}"><x-icon name="pencil" class="!w-4 !h-4" /></button>
        @endif
    </div>
    @if($loc->relationLoaded('children') && $loc->children->isNotEmpty())
        <ul class="tree-children flex flex-col gap-1">
            @foreach($loc->children as $child)
                @include('livewire.assets.partials.location-node', ['loc' => $child])
            @endforeach
        </ul>
    @endif
</li>
