<x-layouts.app :title="__('plant.title')" :heading="__('plant.title')">
<div class="mx-auto flex max-w-7xl flex-col gap-4">
    @if(! $current)
        <section class="card flex flex-col items-center gap-4 px-6 py-12 text-center">
            <span class="grid h-14 w-14 place-items-center rounded-[16px] bg-surface-2 text-accent-fg"><x-icon name="cube" class="!h-7 !w-7" /></span>
            <h2 class="text-xl font-bold">{{ __('plant.empty_title') }}</h2>
            <p class="max-w-prose text-fg-2">{{ $canManage ? __('plant.empty_text') : __('plant.empty_viewer') }}</p>
            @if($canManage)
                <a href="{{ route('plant.create') }}" class="btn btn-primary"><x-icon name="plus" />{{ __('plant.new') }}</a>
            @endif
        </section>
    @else
        @php($counts = $data['counts'])
        <div x-data="plantView(@js($data), { statusUrl: @js(route('plant.status', $current)), error: @js(__('plant.mode.error')), orders: @js([trans_choice('plant.open_orders', 0), trans_choice('plant.open_orders', 1, ['count' => 1]), trans_choice('plant.open_orders', 2, ['count' => ':count'])]) })" class="flex flex-col gap-4">
            {{-- Piante, vista e modifica --}}
            <div class="flex flex-wrap items-center gap-2">
                @if($layouts->count() > 1)
                    <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" aria-label="{{ __('plant.layouts') }}">
                        @foreach($layouts as $l)
                            <a href="{{ route('plant.index', ['pianta' => $l->id]) }}" @class(['status-filter', 'is-active' => $l->id === $current->id]) @if($l->id === $current->id) aria-current="page" @endif>{{ $l->name }}</a>
                        @endforeach
                    </nav>
                @else
                    <h2 class="text-lg font-bold">{{ $current->name }}</h2>
                @endif
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <div class="inline-flex rounded-[var(--radius-md)] border border-line p-0.5" role="group" aria-label="{{ __('plant.mode.label') }}" x-show="can3d" x-cloak>
                        <button type="button" class="btn btn-sm" :class="mode === '3d' ? 'btn-primary' : 'btn-ghost'" :aria-pressed="(mode === '3d').toString()" @click="show3d()">{{ __('plant.mode.3d') }}</button>
                        <button type="button" class="btn btn-sm" :class="mode === '2d' ? 'btn-primary' : 'btn-ghost'" :aria-pressed="(mode === '2d').toString()" @click="show2d()">{{ __('plant.mode.2d') }}</button>
                    </div>
                    @if($canManage)
                        <a href="{{ route('plant.edit', $current) }}" class="btn btn-secondary btn-sm"><x-icon name="pencil" class="!h-4 !w-4" />{{ __('plant.edit') }}</a>
                        <a href="{{ route('plant.create') }}" class="btn btn-ghost btn-sm"><x-icon name="plus" class="!h-4 !w-4" />{{ __('plant.new') }}</a>
                    @endif
                </div>
            </div>

            <div class="alert alert-info" role="status" x-cloak x-show="tier === 'weak' && mode === '2d'"><x-icon name="info" /><span class="flex-1">{{ __('plant.mode.weak') }}</span><button type="button" class="btn btn-secondary btn-sm" @click="show3d()">{{ __('plant.mode.try_3d') }}</button></div>
            <div class="alert alert-info" role="status" x-cloak x-show="tier === 'none'"><x-icon name="info" /><span>{{ __('plant.mode.none') }}</span></div>
            <div class="alert alert-warn" role="alert" x-cloak x-show="error"><x-icon name="alert-triangle" /><span x-text="error"></span></div>
            @if($unplaced > 0)
                <div class="alert alert-info" role="note"><x-icon name="info" /><span class="flex-1">{{ trans_choice('plant.unplaced', $unplaced, ['count' => $unplaced]) }}</span><a href="{{ route('plant.edit', $current) }}" class="btn btn-ghost btn-sm">{{ __('plant.edit') }}</a></div>
            @endif

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                {{-- Vista --}}
                <section class="card relative overflow-hidden" aria-labelledby="p-view">
                    <h2 id="p-view" class="sr-only">{{ $current->name }}</h2>
                    {{-- 3D: altezza fissa; 2D: le proporzioni del pavimento, così sul telefono non resta spazio vuoto --}}
                    <div class="relative" :class="mode === '3d' ? 'h-[58vh] min-h-[320px] lg:h-[68vh]' : 'max-h-[75vh] min-h-[200px]'"
                         :style="mode === '3d' ? '' : 'aspect-ratio: {{ $data['layout']['width'] + 1 }} / {{ $data['layout']['depth'] + 1 }}'"
                         style="aspect-ratio: {{ $data['layout']['width'] + 1 }} / {{ $data['layout']['depth'] + 1 }}">
                        <div x-ref="stage" class="absolute inset-0" x-show="mode === '3d'" x-cloak></div>
                        <div class="absolute inset-0 grid place-items-center text-sm text-muted" x-show="loading" x-cloak role="status">{{ __('plant.mode.loading') }}</div>
                        <div class="absolute inset-0 p-2 sm:p-3" x-show="mode !== '3d'">
                            <x-plant-plan :data="$data" />
                        </div>
                    </div>
                    <p class="border-t border-line-soft px-4 py-2 text-xs text-muted">
                        <span x-show="mode === '3d'" x-cloak>{{ __('plant.mode.help_3d') }}</span>
                        <span x-show="mode !== '3d'">{{ __('plant.mode.help_2d') }}</span>
                        · {{ __('plant.updated') }}
                    </p>
                </section>

                {{-- Dettaglio della macchina scelta --}}
                <aside class="card flex flex-col gap-3 p-4" x-ref="panel" aria-live="polite" aria-labelledby="p-panel">
                    <h2 id="p-panel" class="text-sm font-semibold text-fg-2">{{ __('plant.machines') }}</h2>
                    <template x-if="selected">
                        <div class="flex flex-col gap-3">
                            <div>
                                <p class="font-mono text-sm font-bold" x-text="selected.code"></p>
                                <p class="text-lg font-bold leading-tight" x-text="selected.name"></p>
                                <p class="text-sm text-muted" x-text="selected.category || ''"></p>
                            </div>
                            <span class="status self-start" :class="'status-' + selected.tone"><span x-text="selected.status_label"></span></span>
                            <template x-if="selected.open_orders !== null">
                                <p class="text-sm" x-text="ordersText(selected.open_orders)"></p>
                            </template>
                            <div class="flex flex-col gap-2">
                                <a :href="selected.url" class="btn btn-primary"><x-icon name="factory" />{{ __('plant.open_asset') }}</a>
                                <template x-if="selected.orders_url"><a :href="selected.orders_url" class="btn btn-secondary"><x-icon name="clipboard-list" />{{ __('plant.open_orders_link') }}</a></template>
                                <template x-if="selected.report_url"><a :href="selected.report_url" class="btn btn-secondary"><x-icon name="alert-triangle" />{{ __('plant.report') }}</a></template>
                                <button type="button" class="btn btn-ghost btn-sm" @click="select(null)">{{ __('plant.close') }}</button>
                            </div>
                        </div>
                    </template>
                    <p class="text-sm text-muted" x-show="! selected">{{ __('plant.select_hint') }}</p>
                    {{-- Legenda con i conteggi --}}
                    <div class="mt-auto flex flex-col gap-1.5 border-t border-line-soft pt-3" role="list" aria-label="{{ __('plant.legend') }}">
                        @foreach(\App\Enums\AssetStatus::cases() as $s)
                            @continue($s === \App\Enums\AssetStatus::Retired)
                            <div class="flex items-center justify-between gap-2 text-sm" role="listitem"><x-status :status="$s" /><span class="num font-bold">{{ $counts[$s->value] ?? 0 }}</span></div>
                        @endforeach
                    </div>
                </aside>
            </div>

            {{-- Elenco accessibile delle macchine della pianta (stessi dati della vista) --}}
            <section class="card overflow-hidden" aria-labelledby="p-list">
                <h2 id="p-list" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('plant.machines') }} <span class="text-sm font-normal text-muted">· {{ trans_choice('plant.machines_count', count($data['items']), ['count' => count($data['items'])]) }}</span></h2>
                @if($data['items'] === [])
                    <p class="px-4 py-6 text-center text-muted">{{ __('plant.empty_layout') }}</p>
                @else
                    <ul class="divide-y divide-line-soft">
                        <template x-for="item in sortedItems" :key="item.id">
                            <li class="flex items-center gap-3 px-4 py-2.5" :class="selectedId === item.id ? 'bg-surface-2' : ''">
                                <button type="button" class="flex min-h-[44px] flex-1 items-center gap-3 text-left" @click="select(item.id)" :aria-pressed="(selectedId === item.id).toString()">
                                    <span class="font-mono text-sm font-bold" x-text="item.code"></span>
                                    <span class="min-w-0 flex-1 truncate" x-text="item.name"></span>
                                </button>
                                <span class="status" :class="'status-' + item.tone"><span x-text="item.status_label"></span></span>
                            </li>
                        </template>
                    </ul>
                @endif
            </section>
        </div>
    @endif
</div>
</x-layouts.app>
