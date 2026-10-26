{{-- Avvisi a comparsa: Livewire->dispatch('toast', message: '…', tone: 'ok|warn|ko') --}}
<div x-data="{ items: [], add(e) { const id = Date.now() + Math.random(); this.items.push({ id, message: e.detail.message, tone: e.detail.tone || 'ok' }); setTimeout(() => this.items = this.items.filter(i => i.id !== id), 5000); } }"
     @toast.window="add($event)"
     class="pointer-events-none fixed inset-x-0 z-[60] flex flex-col items-center gap-2 px-4"
     style="bottom: calc(var(--mt-bottomnav-h) + var(--safe-bottom) + 0.75rem)" aria-live="polite">
    <template x-for="item in items" :key="item.id">
        <div x-transition class="glass pointer-events-auto flex w-full max-w-md items-center gap-3 rounded-[14px] border border-line px-4 py-3 shadow-pop"
             :class="{ 'text-fg': true }">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background: var(--mt-${item.tone})`"></span>
            <span class="flex-1 text-sm" x-text="item.message"></span>
            <button type="button" class="text-muted" @click="items = items.filter(i => i.id !== item.id)" aria-label="{{ __('app.close') }}">✕</button>
        </div>
    </template>
</div>
