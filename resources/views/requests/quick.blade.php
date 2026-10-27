{{-- Segnalazione rapida: Alpine puro (niente Livewire), così funziona anche aperta dalla cache senza rete --}}
<x-layouts.app :title="__('maintenance.quick.title')" :heading="__('maintenance.quick.title')">
<div class="mx-auto flex max-w-xl flex-col gap-4" x-data="quickReport(@js($config))" x-cloak>

    {{-- Senza rete --}}
    <div x-show="!online" class="alert alert-warn" role="status"><x-icon name="wifi-off" /><span>{{ __('maintenance.quick.offline') }}</span></div>

    {{-- 1. Quale macchina --}}
    <section x-show="step === 'asset'" class="flex flex-col gap-4" aria-labelledby="q-asset">
        <h2 id="q-asset" class="text-xl font-bold">{{ __('maintenance.quick.which') }}</h2>

        <button type="button" class="btn btn-primary btn-xl w-full" @click="scan()">
            <x-icon name="qr" class="!w-6 !h-6" />{{ __('maintenance.quick.scan') }}
        </button>
        <input type="file" accept="image/*" capture="environment" class="sr-only" x-ref="qrFile" @change="scanPhoto($event)" aria-hidden="true" tabindex="-1">
        <p x-show="scanError" x-text="scanError" class="field-error" role="alert"></p>

        <div class="field">
            <label for="q-search" class="field-label">{{ __('maintenance.quick.search') }}</label>
            <input id="q-search" type="search" x-model="query" class="input text-lg" placeholder="{{ __('maintenance.quick.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
        </div>

        <ul x-show="results.length" class="card overflow-hidden" role="listbox" aria-label="{{ __('maintenance.quick.results') }}">
            <template x-for="a in results" :key="a.id">
                <li class="border-b border-line-soft last:border-0">
                    <button type="button" class="flex min-h-14 w-full items-center gap-3 px-4 py-2 text-left hover:bg-surface-2" @click="pick(a)">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold" x-text="a.name"></span>
                            <span class="block truncate text-sm text-muted"><span class="font-mono" x-text="a.code"></span><span x-show="a.loc" x-text="' · ' + a.loc"></span></span>
                        </span>
                        <x-icon name="chevron-right" class="text-muted" />
                    </button>
                </li>
            </template>
        </ul>
        <p x-show="query.trim() && !results.length" class="text-sm text-muted">{{ __('maintenance.quick.no_results') }}</p>

        <div x-show="!query.trim() && recents.length" class="flex flex-col gap-2">
            <h3 class="text-sm font-semibold text-muted">{{ __('maintenance.quick.recent') }}</h3>
            <template x-for="a in recents" :key="'r' + a.id">
                <button type="button" class="card flex min-h-14 items-center gap-3 px-4 py-2 text-left hover:bg-surface-2" @click="pick(a)">
                    <x-icon name="history" class="text-muted" />
                    <span class="min-w-0 flex-1"><span class="block truncate font-semibold" x-text="a.name"></span><span class="block font-mono text-sm text-muted" x-text="a.code"></span></span>
                </button>
            </template>
        </div>

        <button type="button" class="btn btn-ghost self-start" @click="chooseNoAsset()"><x-icon name="map-pin" />{{ __('maintenance.quick.no_asset_button') }}</button>
    </section>

    {{-- Lettore QR a tutto schermo --}}
    <div x-show="scanning" class="fixed inset-0 z-50 flex flex-col bg-black" role="dialog" aria-modal="true" aria-label="{{ __('maintenance.quick.scan') }}" @keydown.escape.window="closeScan()">
        <video x-ref="video" class="h-full w-full object-cover" playsinline muted></video>
        <div class="pointer-events-none absolute inset-0 grid place-items-center">
            <div class="h-64 w-64 rounded-3xl border-4 border-white/90 shadow-[0_0_0_9999px_rgba(0,0,0,.45)]"></div>
        </div>
        <p class="absolute inset-x-0 text-center text-lg font-semibold text-white" style="top: calc(var(--safe-top) + 1.5rem)">{{ __('maintenance.quick.scan_hint') }}</p>
        <div class="absolute inset-x-0 flex justify-center gap-3 px-4" style="bottom: calc(var(--safe-bottom) + 1.5rem)">
            <button type="button" class="btn btn-secondary btn-xl" @click="closeScan(); $refs.qrFile.click()"><x-icon name="camera" />{{ __('maintenance.quick.scan_photo') }}</button>
            <button type="button" class="btn btn-primary btn-xl" @click="closeScan()">{{ __('app.cancel') }}</button>
        </div>
    </div>

    {{-- 2. Cosa succede --}}
    <form x-show="step === 'details'" @submit.prevent="submit()" class="flex flex-col gap-5" novalidate aria-labelledby="q-details">
        <h2 id="q-details" class="sr-only">{{ __('maintenance.quick.details') }}</h2>

        <div class="card flex items-center gap-3 p-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[10px] bg-surface-2 text-accent-fg"><x-icon name="factory" /></span>
            <div class="min-w-0 flex-1">
                <template x-if="asset">
                    <div><div class="truncate font-semibold" x-text="asset.name"></div><div class="truncate text-sm text-muted"><span class="font-mono" x-text="asset.code"></span><span x-show="asset.loc" x-text="' · ' + asset.loc"></span></div></div>
                </template>
                <template x-if="!asset"><div class="font-semibold">{{ __('maintenance.quick.no_asset') }}</div></template>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" @click="back()">{{ __('maintenance.quick.change') }}</button>
        </div>
        <p x-show="errors.asset" x-text="errors.asset" class="field-error" role="alert"></p>

        <div x-show="noAsset" class="field">
            <label for="q-loc" class="field-label">{{ __('maintenance.quick.where') }}</label>
            <select id="q-loc" x-model="locationId" class="input">
                <option value="">{{ __('maintenance.quick.where_unknown') }}</option>
                @foreach($locations as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
            </select>
        </div>

        <div class="field">
            <label for="q-desc" class="field-label text-base">{{ __('maintenance.quick.what') }}</label>
            <textarea id="q-desc" x-ref="description" x-model="description" rows="3" maxlength="2000" class="input py-3 text-lg" placeholder="{{ __('maintenance.quick.what_placeholder') }}" :aria-invalid="errors.description ? 'true' : null"></textarea>
            <div class="mt-1 flex flex-wrap gap-2" aria-label="{{ __('maintenance.quick.chips_label') }}">
                @foreach($chips as $chip)
                    <button type="button" class="status-filter !min-h-10" @click="addChip(@js($chip))">{{ $chip }}</button>
                @endforeach
            </div>
            <p x-show="errors.description" x-text="errors.description" class="field-error" role="alert"></p>
        </div>

        <fieldset x-show="asset" class="flex flex-col gap-2">
            <legend class="field-label mb-2 text-base">{{ __('maintenance.quick.stopped') }}</legend>
            <div class="grid grid-cols-2 gap-3">
                <button type="button" class="btn btn-xl" :class="stopped === true ? 'btn-danger' : 'btn-secondary'" @click="stopped = true" :aria-pressed="(stopped === true).toString()"><x-icon name="pause-circle" />{{ __('maintenance.quick.stopped_yes') }}</button>
                <button type="button" class="btn btn-xl" :class="stopped === false ? 'btn-primary' : 'btn-secondary'" @click="stopped = false" :aria-pressed="(stopped === false).toString()"><x-icon name="check-circle" />{{ __('maintenance.quick.stopped_no') }}</button>
            </div>
            <p x-show="errors.stopped" x-text="errors.stopped" class="field-error" role="alert"></p>
        </fieldset>

        <fieldset class="flex flex-col gap-2">
            <legend class="field-label mb-2 text-base">{{ __('maintenance.quick.urgency') }}</legend>
            <div class="grid grid-cols-4 gap-2" role="radiogroup">
                @foreach($priorities as $p)
                    <label class="choice !flex-col !items-center !gap-1 !p-2 text-center" style="--c: var(--mt-{{ $p->tone() }})">
                        <input type="radio" name="priority" value="{{ $p->value }}" x-model="priority" class="sr-only">
                        <x-priority :priority="$p" class="!flex-col !gap-1" />
                    </label>
                @endforeach
            </div>
            <p class="text-sm text-muted">{{ __('maintenance.quick.urgency_hint') }}</p>
        </fieldset>

        <div x-show="priority === 'critica' || (asset && asset.a && stopped)" class="alert alert-ko" role="note">
            <x-icon name="bell" /><span>{{ __('maintenance.quick.urgent_notice') }}</span>
        </div>

        <div class="flex flex-col gap-2">
            <span class="field-label text-base">{{ __('maintenance.quick.photos') }}</span>
            <div class="flex flex-wrap gap-2">
                <template x-for="(p, i) in photos" :key="p.url">
                    <div class="relative h-24 w-24 overflow-hidden rounded-[var(--radius-md)] bg-surface-2">
                        <img :src="p.url" alt="" class="h-full w-full object-cover">
                        <button type="button" class="btn btn-icon btn-sm absolute right-1 top-1 !min-h-8 !w-8 bg-black/60 text-white" @click="removePhoto(i)" aria-label="{{ __('maintenance.quick.remove_photo') }}"><x-icon name="x" class="!w-4 !h-4" /></button>
                    </div>
                </template>
                <label x-show="photos.length < 6" class="grid h-24 w-24 cursor-pointer place-items-center rounded-[var(--radius-md)] border-2 border-dashed border-line text-muted hover:bg-surface-2">
                    <span class="flex flex-col items-center gap-1 text-xs"><x-icon name="camera" class="!w-7 !h-7" />{{ __('maintenance.quick.add_photo') }}</span>
                    <input type="file" accept="image/*" multiple class="sr-only" @change="addPhotos($event)">
                </label>
            </div>
            <p x-show="preparing" class="text-sm text-muted">{{ __('assets.files.preparing') }}</p>
        </div>

        <p x-show="errors.general" x-text="errors.general" class="alert alert-ko" role="alert"></p>

        <div class="sticky z-10 flex flex-col gap-2 pb-1" style="bottom: calc(var(--mt-bottomnav-h) + var(--safe-bottom) + .5rem)">
            <button type="submit" class="btn btn-primary btn-xl w-full shadow-lg" :disabled="sending || preparing">
                <x-icon name="arrow-right" /><span x-text="sending ? @js(__('maintenance.quick.sending')) : (online ? @js(__('maintenance.quick.send')) : @js(__('maintenance.quick.save_offline')))"></span>
            </button>
        </div>
    </form>

    {{-- 3. Fatto --}}
    <section x-show="step === 'done'" class="card flex flex-col items-center gap-4 px-5 py-8 text-center" role="status" aria-live="polite">
        <template x-if="result && !result.queued">
            <div class="flex flex-col items-center gap-3">
                <span class="grid h-16 w-16 place-items-center rounded-full bg-surface-2 text-ok"><x-icon name="check-circle" class="!w-9 !h-9" /></span>
                <h2 class="text-xl font-bold">{{ __('maintenance.quick.done') }}</h2>
                <p class="font-mono text-lg" x-text="result.number"></p>
                <p class="text-fg-2" x-text="result.urgent ? @js(__('maintenance.quick.done_urgent')) : @js(__('maintenance.quick.done_text'))"></p>
                <template x-for="e in (result.photo_errors || [])" :key="e"><p class="text-sm text-warn" x-text="e"></p></template>
                <a :href="result.url" class="btn btn-secondary">{{ __('maintenance.quick.follow') }}</a>
            </div>
        </template>
        <template x-if="result && result.queued">
            <div class="flex flex-col items-center gap-3">
                <span class="grid h-16 w-16 place-items-center rounded-full bg-surface-2 text-warn"><x-icon name="clock" class="!w-9 !h-9" /></span>
                <h2 class="text-xl font-bold">{{ __('maintenance.quick.queued') }}</h2>
                <p class="text-fg-2" x-text="result.expired ? @js(__('maintenance.quick.queued_login')) : @js(__('maintenance.quick.queued_text'))"></p>
            </div>
        </template>
        <button type="button" class="btn btn-primary btn-xl" @click="restart()"><x-icon name="plus" />{{ __('maintenance.quick.another') }}</button>
    </section>

    {{-- Segnalazioni ancora sul telefono --}}
    <section x-show="pendingItems.length" class="card overflow-hidden" aria-labelledby="q-pending">
        <div class="flex items-center gap-2 border-b border-line-soft px-4 py-3">
            <h2 id="q-pending" class="flex-1 text-base font-bold">{{ __('maintenance.quick.pending_title') }}</h2>
            <button type="button" class="btn btn-secondary btn-sm" @click="retryPending()" :disabled="!online"><x-icon name="refresh" class="!w-4 !h-4" />{{ __('maintenance.quick.retry') }}</button>
        </div>
        <ul>
            <template x-for="item in pendingItems" :key="item.uuid">
                <li class="flex items-start gap-3 border-b border-line-soft px-4 py-3 last:border-0">
                    <x-icon name="clock" class="mt-0.5 text-warn" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium" x-text="item.label"></div>
                        <div class="truncate text-sm text-muted" x-text="new Date(item.createdAt).toLocaleString('it-IT') + ' · ' + item.fields.description"></div>
                        <div x-show="item.failed" class="text-sm text-ko" x-text="item.message || @js(__('maintenance.quick.js.send_error'))"></div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-icon btn-sm text-ko" @click="discard(item.uuid)" aria-label="{{ __('maintenance.quick.discard') }}"><x-icon name="trash" class="!w-4 !h-4" /></button>
                </li>
            </template>
        </ul>
    </section>
</div>
</x-layouts.app>
