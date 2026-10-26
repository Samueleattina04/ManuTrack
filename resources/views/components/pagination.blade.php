{{-- Paginazione semplice per Livewire (precedente / successiva) --}}
@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3" aria-label="{{ __('app.pagination') }}">
        <span class="text-sm text-muted num">{{ __('app.pagination_info', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</span>
        <span class="flex gap-2">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="previousPage('{{ $paginator->getPageName() }}')" @disabled($paginator->onFirstPage())><x-icon name="chevron-right" class="rotate-180 !w-4 !h-4" />{{ __('app.previous') }}</button>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="nextPage('{{ $paginator->getPageName() }}')" @disabled(! $paginator->hasMorePages())>{{ __('app.next') }}<x-icon name="chevron-right" class="!w-4 !h-4" /></button>
        </span>
    </nav>
@endif
