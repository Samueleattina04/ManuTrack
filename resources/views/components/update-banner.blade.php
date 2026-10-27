{{-- "Nuova versione disponibile": compare quando il service worker ha scaricato un aggiornamento --}}
<div x-data="swUpdate" x-cloak x-show="worker" x-transition
     class="fixed inset-x-0 z-[70] flex justify-center px-4"
     style="top: calc(var(--safe-top) + 0.75rem)" role="status" aria-live="polite">
    <div class="glass flex w-full max-w-md items-center gap-3 rounded-[14px] border border-line px-4 py-3 shadow-pop">
        <x-icon name="refresh" class="text-accent-fg" />
        <div class="min-w-0 flex-1 text-sm"><strong class="block">{{ __('pwa.update.title') }}</strong><span class="text-muted">{{ __('pwa.update.text') }}</span></div>
        <button type="button" class="btn btn-primary btn-sm" @click="apply()">{{ __('pwa.update.action') }}</button>
        <button type="button" class="btn btn-ghost btn-icon !w-9 !min-h-9" @click="worker = null" aria-label="{{ __('pwa.update.later') }}"><x-icon name="x" class="!w-4 !h-4" /></button>
    </div>
</div>
