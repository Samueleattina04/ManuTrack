@php
    $fmt = fn ($v) => $v === null ? '—' : \App\Actions\Assets\RecordMeterReading::format((float) $v);
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <a href="{{ route('assets.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('assets.title') }}</a>

    {{-- Intestazione --}}
    <section class="card grid grid-cols-[5.5rem_minmax(0,1fr)] gap-x-4 gap-y-4 p-4 sm:p-5 md:grid-cols-[15rem_minmax(0,1fr)]">
        <div class="md:row-span-2">
            @if($asset->cover)
                <a href="{{ $asset->cover->url() }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-[var(--radius-md)]">
                    <img src="{{ $asset->cover->url('thumb') }}" alt="{{ __('assets.files.photo_of', ['name' => $asset->name]) }}" class="aspect-square w-full object-cover md:aspect-[4/3]">
                </a>
            @else
                <div class="grid aspect-square w-full place-items-center rounded-[var(--radius-md)] bg-surface-2 text-muted md:aspect-[4/3]">
                    <x-icon :name="$asset->category?->icon ?? 'factory'" class="!w-8 !h-8 md:!w-12 md:!h-12" />
                </div>
            @endif
        </div>
        <div class="flex min-w-0 flex-col gap-2 self-center md:self-start">
            <div class="font-mono text-sm text-muted">{{ $asset->code }}</div>
            <h2 class="text-xl font-bold leading-tight sm:text-2xl">{{ $asset->name }}</h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-status :status="$asset->status" />
                <span class="inline-flex items-center gap-1.5 text-sm text-fg-2"><x-criticality :value="$asset->criticality" /><span class="hidden sm:inline">{{ $asset->criticality->label() }}</span></span>
                @if($asset->category)<span class="hidden items-center gap-1.5 text-sm text-fg-2 sm:inline-flex"><x-icon :name="$asset->category->icon" class="!w-4 !h-4" />{{ $asset->category->name }}</span>@endif
            </div>
        </div>
        <div class="col-span-2 flex min-w-0 flex-col gap-3 md:col-span-1 md:col-start-2">
            <dl class="grid gap-1.5 text-sm">
                @if($asset->category)
                    <div class="flex items-start gap-2 sm:hidden"><dt><x-icon :name="$asset->category->icon" class="!w-4 !h-4 text-muted" label="{{ __('assets.field.asset_category_id') }}" /></dt><dd>{{ $asset->category->name }} · {{ $asset->criticality->label() }}</dd></div>
                @endif
                <div class="flex items-start gap-2"><dt><x-icon name="map-pin" class="!w-4 !h-4 text-muted" label="{{ __('assets.field.location_id') }}" /></dt><dd>{{ $asset->location?->fullName() ?? __('assets.form.no_location') }}</dd></div>
                @if($asset->parent)
                    <div class="flex items-start gap-2"><dt><x-icon name="layers" class="!w-4 !h-4 text-muted" label="{{ __('assets.field.parent_id') }}" /></dt><dd>{{ __('assets.part_of') }} <a href="{{ route('assets.show', $asset->parent) }}" class="font-medium underline">{{ $asset->parent->code }} · {{ $asset->parent->name }}</a></dd></div>
                @endif
                @if($asset->status_changed_at)
                    <div class="flex items-start gap-2"><dt><x-icon name="clock" class="!w-4 !h-4 text-muted" label="{{ __('assets.status.since_label') }}" /></dt><dd class="text-fg-2">{{ __('assets.status.since', ['status' => $asset->status->label(), 'time' => $asset->status_changed_at->diffForHumans()]) }}</dd></div>
                @endif
            </dl>
            <div class="flex flex-wrap gap-2">
                @can('requests.create')<a href="{{ route('requests.create', ['id' => $asset->id]) }}" class="btn btn-primary"><x-icon name="alert-triangle" />{{ __('maintenance.quick.title') }}</a>@endcan
                @if($can['manage'])<a href="{{ route('assets.edit', $asset) }}" class="btn btn-secondary"><x-icon name="pencil" />{{ __('app.edit') }}</a>@endif
                <a href="{{ route('assets.labels', ['ids' => $asset->id, 'formato' => 'grandi']) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="printer" />{{ __('assets.qr.print_one') }}</a>
                @if($can['manage'])<a href="{{ route('assets.create', ['padre' => $asset->id]) }}" class="btn btn-ghost"><x-icon name="plus" />{{ __('assets.add_child') }}</a>@endif
            </div>
        </div>
    </section>

    {{-- Cambio di stato --}}
    @if($can['status'])
        <section class="card flex flex-col gap-3 p-4 sm:p-5" aria-labelledby="s-status">
            <h2 id="s-status" class="text-lg font-bold">{{ __('assets.status.title') }}</h2>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-5" role="radiogroup" aria-labelledby="s-status">
                @foreach($statuses as $s)
                    <label @class(['choice !items-center !p-2.5', 'opacity-60' => $s === $asset->status]) style="--c: var(--mt-{{ $s->tone() }})">
                        <input type="radio" name="newStatus" value="{{ $s->value }}" wire:model.live="newStatus" class="sr-only" @disabled($s === $asset->status)>
                        <x-icon :name="$s->icon()" class="text-[var(--c)]" />
                        <span class="text-sm font-semibold">{{ $s->label() }}@if($s === $asset->status)<span class="block text-xs font-normal text-muted">{{ __('assets.status.current') }}</span>@endif</span>
                    </label>
                @endforeach
            </div>
            @if($newStatus !== '')
                @php $target = \App\Enums\AssetStatus::from($newStatus); $down = in_array($target, [\App\Enums\AssetStatus::Fault, \App\Enums\AssetStatus::Stopped], true); @endphp
                <form wire:submit="changeStatus" class="flex flex-col gap-3">
                    <div class="field">
                        <label for="s-note" class="field-label">{{ __('assets.status.note') }}</label>
                        <input id="s-note" type="text" wire:model="statusNote" maxlength="200" class="input" placeholder="{{ __('assets.status.note_placeholder') }}">
                    </div>
                    @if($down && $asset->stopsLine())
                        <div class="alert alert-ko" role="note"><x-icon name="bell" /><span>{{ __('assets.status.urgent_notice') }}</span></div>
                    @elseif($down)
                        <div class="alert alert-warn" role="note"><x-icon name="bell" /><span>{{ __('assets.status.notice') }}</span></div>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary btn-xl" wire:loading.attr="disabled"><x-icon :name="$target->icon()" />{{ __('assets.status.confirm', ['status' => $target->label()]) }}</button>
                        <button type="button" class="btn btn-ghost" wire:click="$set('newStatus', '')">{{ __('app.cancel') }}</button>
                    </div>
                </form>
            @endif
        </section>
    @endif

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="flex min-w-0 flex-col gap-5">
            {{-- Storico interventi --}}
            @if($orderHistory->isNotEmpty() || $openRequests->isNotEmpty() || auth()->user()->can('work_orders.view'))
                <section class="card overflow-hidden" aria-labelledby="s-history">
                    <div class="flex flex-wrap items-center gap-2 border-b border-line-soft px-4 py-3">
                        <h2 id="s-history" class="flex-1 text-base font-bold">{{ __('maintenance.asset.history') }}</h2>
                        @if($downtimeMinutes > 0)<span class="text-sm text-muted">{{ __('maintenance.asset.downtime_90', ['hours' => number_format($downtimeMinutes / 60, 1, ',', '.')]) }}</span>@endif
                        @can('work_orders.create')<a href="{{ route('work-orders.create', ['macchina' => $asset->id]) }}" class="btn btn-ghost btn-sm"><x-icon name="plus" class="!w-4 !h-4" />{{ __('maintenance.order.new') }}</a>@endcan
                    </div>
                    <ul>
                        @foreach($openRequests as $r)
                            <li class="border-b border-line-soft"><a href="{{ route('requests.show', $r) }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface-2">
                                <x-icon name="bell" class="text-warn" /><span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $r->summary(80) }}</span><span class="block text-xs text-muted"><span class="font-mono">{{ $r->number }}</span> · {{ $r->reported_at?->diffForHumans() }}</span></span><x-status :status="$r->status" />
                            </a></li>
                        @endforeach
                        @forelse($orderHistory as $wo)
                            <li class="border-b border-line-soft last:border-0"><a href="{{ route('work-orders.show', $wo) }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface-2">
                                <x-icon :name="$wo->type->icon()" class="text-muted" />
                                <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $wo->title }}</span><span class="block text-xs text-muted"><span class="font-mono">{{ $wo->number }}</span> · {{ ($wo->completed_at ?? $wo->created_at)->translatedFormat('j M Y') }}@if($wo->assignees->isNotEmpty()) · {{ $wo->assignees->pluck('name')->implode(', ') }}@endif</span></span>
                                <x-status :status="$wo->status" />
                            </a></li>
                        @empty
                            @if($openRequests->isEmpty())<li class="px-4 py-3 text-sm text-muted">{{ __('maintenance.asset.no_history') }}</li>@endif
                        @endforelse
                    </ul>
                    @if($historyCount > $orderHistory->count())
                        <a href="{{ route('work-orders.index', ['macchina' => $asset->id, 'vista' => 'tutti', 'miei' => 0]) }}" class="block border-t border-line-soft px-4 py-2.5 text-sm font-medium text-accent-fg hover:bg-surface-2">{{ __('maintenance.asset.all_orders', ['count' => $historyCount]) }}</a>
                    @endif
                </section>
            @endif

            {{-- Contatori --}}
            <section class="card overflow-hidden" aria-labelledby="s-meters">
                <div class="flex items-center gap-2 border-b border-line-soft px-4 py-3">
                    <h2 id="s-meters" class="flex-1 text-base font-bold">{{ __('assets.meters.title') }}</h2>
                    @if($can['manage'])<button type="button" class="btn btn-ghost btn-sm" wire:click="editMeter"><x-icon name="plus" class="!w-4 !h-4" />{{ __('assets.meters.add') }}</button>@endif
                </div>

                @if($editingMeter !== null)
                    <form wire:submit="saveMeter" class="grid gap-3 border-b border-line-soft bg-surface-2/40 p-4 sm:grid-cols-[1fr_9rem]">
                        <x-form.input name="meterForm.name" :label="__('assets.meters.name')" wire:model="meterForm.name" :placeholder="__('assets.meters.name_placeholder')" />
                        <div class="field">
                            <label for="m-unit" class="field-label">{{ __('assets.meters.unit') }}</label>
                            <input id="m-unit" list="m-units" wire:model="meterForm.unit" class="input" maxlength="20">
                            <datalist id="m-units">@foreach(\App\Models\Meter::UNITS as $u)<option value="{{ $u }}">@endforeach</datalist>
                            @error('meterForm.unit')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <label class="flex items-start gap-3 text-sm sm:col-span-2">
                            <input type="checkbox" wire:model="meterForm.cumulative" class="mt-0.5 h-5 w-5 accent-[var(--mt-accent)]">
                            <span><span class="font-medium">{{ __('assets.meters.cumulative') }}</span><span class="block text-muted">{{ __('assets.meters.cumulative_hint') }}</span></span>
                        </label>
                        <div class="flex flex-wrap gap-2 sm:col-span-2">
                            <button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('app.save') }}</button>
                            <button type="button" class="btn btn-ghost" wire:click="$set('editingMeter', null)">{{ __('app.cancel') }}</button>
                            @if($editingMeter)<button type="button" class="btn btn-ghost ml-auto text-ko" wire:click="deleteMeter({{ $editingMeter }})" wire:confirm="{{ __('assets.meters.delete_confirm') }}"><x-icon name="trash" />{{ __('app.delete') }}</button>@endif
                        </div>
                    </form>
                @endif

                @forelse($meters as $m)
                    <div class="border-b border-line-soft p-4 last:border-0" wire:key="m-{{ $m->id }}">
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <h3 class="font-semibold">{{ $m->name }}</h3>
                            <span class="num text-2xl font-bold">{{ $fmt($m->current_value) }}</span><span class="text-muted">{{ $m->unit }}</span>
                            <span class="text-xs text-muted">{{ $m->last_read_at ? __('assets.meters.last', ['time' => $m->last_read_at->diffForHumans()]) : __('assets.meters.never') }}</span>
                            <span class="ml-auto flex gap-1">
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="toggleHistory({{ $m->id }})" aria-expanded="{{ $historyFor === $m->id ? 'true' : 'false' }}"><x-icon name="history" class="!w-4 !h-4" />{{ __('assets.meters.history') }}</button>
                                @if($can['manage'])<button type="button" class="btn btn-ghost btn-sm btn-icon" wire:click="editMeter({{ $m->id }})" aria-label="{{ __('app.edit') }} {{ $m->name }}"><x-icon name="pencil" class="!w-4 !h-4" /></button>@endif
                            </span>
                        </div>
                        @if($can['readings'])
                            <form wire:submit="recordReading({{ $m->id }})" class="mt-3 flex flex-wrap items-start gap-2">
                                <div class="field min-w-[10rem] flex-1">
                                    <label for="r-{{ $m->id }}" class="sr-only">{{ __('assets.meters.new_reading', ['name' => $m->name]) }}</label>
                                    <div class="relative">
                                        <input id="r-{{ $m->id }}" type="text" inputmode="decimal" wire:model="reading.{{ $m->id }}" class="input pr-16 num text-lg" placeholder="{{ __('assets.meters.reading_placeholder') }}" autocomplete="off" @error('reading.'.$m->id) aria-invalid="true" aria-describedby="r-{{ $m->id }}-err" @enderror>
                                        <span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm text-muted">{{ $m->unit }}</span>
                                    </div>
                                    @error('reading.'.$m->id)<span id="r-{{ $m->id }}-err" class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $message }}</span>@enderror
                                </div>
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="recordReading({{ $m->id }})"><x-icon name="check" />{{ __('assets.meters.record') }}</button>
                                @if($m->cumulative)
                                    <label class="flex w-full items-center gap-2 text-sm text-fg-2">
                                        <input type="checkbox" wire:model="readingReset.{{ $m->id }}" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('assets.meters.reset') }}
                                    </label>
                                @endif
                            </form>
                        @endif
                        @if($historyFor === $m->id && $history)
                            <table class="table mt-3">
                                <thead><tr><th>{{ __('assets.meters.when') }}</th><th class="text-right">{{ __('assets.meters.value') }}</th><th>{{ __('assets.meters.who') }}</th></tr></thead>
                                <tbody>
                                @forelse($history as $r)
                                    <tr>
                                        <td class="num whitespace-nowrap">{{ $r->read_at->translatedFormat('j M Y H:i') }}</td>
                                        <td class="num text-right font-semibold">{{ $fmt($r->value) }} {{ $m->unit }}@if($r->is_reset) <x-status tone="warn" class="ml-1">{{ __('assets.meters.reset_short') }}</x-status>@endif</td>
                                        <td class="text-fg-2">{{ $r->user?->name ?? ($r->source === 'import' ? __('assets.meters.imported') : '—') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-4 text-center text-muted">{{ __('assets.meters.no_readings') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        @endif
                    </div>
                @empty
                    <p class="p-4 text-sm text-muted">{{ __('assets.meters.none') }}</p>
                @endforelse
            </section>

            {{-- Foto --}}
            <section class="card overflow-hidden" aria-labelledby="s-photos">
                <div class="flex flex-wrap items-center gap-2 border-b border-line-soft px-4 py-3">
                    <h2 id="s-photos" class="flex-1 text-base font-bold">{{ __('assets.files.photos') }} <span class="font-normal text-muted">{{ $gallery->count() }}</span></h2>
                    @if($can['attach'])
                        <label class="btn btn-secondary btn-sm cursor-pointer">
                            <x-icon name="camera" class="!w-4 !h-4" />{{ __('assets.files.add_photos') }}
                            <input type="file" class="sr-only" x-data x-photo-upload="photos" accept="image/*" multiple>
                        </label>
                    @endif
                </div>
                <div x-data="{ busy: false }" x-show="busy" x-cloak @photo-preparing.window="busy = true" @photo-uploaded.window="busy = false" class="px-4 pt-3 text-sm text-muted">{{ __('assets.files.preparing') }}</div>
                <div wire:loading wire:target="photos" class="px-4 pt-3 text-sm text-muted">{{ __('assets.files.uploading') }}</div>
                @error('photos')<div class="px-4 pt-3"><span class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $message }}</span></div>@enderror
                @error('photos.*')<div class="px-4 pt-3"><span class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $message }}</span></div>@enderror
                @if($gallery->isEmpty())
                    <p class="p-4 text-sm text-muted">{{ __('assets.files.no_photos') }}</p>
                @else
                    <ul class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach($gallery as $i => $p)
                            <li class="group relative overflow-hidden rounded-[var(--radius-md)] bg-surface-2" wire:key="p-{{ $p->id }}">
                                <a href="{{ $p->url() }}" target="_blank" rel="noopener" class="block">
                                    <img src="{{ $p->url('thumb') }}" alt="{{ $p->original_name }}" loading="lazy" decoding="async" class="aspect-square w-full object-cover">
                                </a>
                                @if($i === 0)<span class="absolute left-2 top-2 rounded-full bg-black/60 px-2 py-0.5 text-xs font-semibold text-white">{{ __('assets.files.cover') }}</span>@endif
                                @if($can['attach'])
                                    <div class="absolute inset-x-0 bottom-0 flex justify-end gap-1 bg-gradient-to-t from-black/70 to-transparent p-1.5">
                                        @if($i !== 0)<button type="button" class="btn btn-icon btn-sm !min-h-9 !w-9 bg-black/40 text-white" wire:click="makeCover({{ $p->id }})" aria-label="{{ __('assets.files.make_cover') }}" title="{{ __('assets.files.make_cover') }}"><x-icon name="star" class="!w-4 !h-4" /></button>@endif
                                        @if($can['manage'] || $p->user_id === auth()->id())
                                            <button type="button" class="btn btn-icon btn-sm !min-h-9 !w-9 bg-black/40 text-white" wire:click="deleteAttachment({{ $p->id }})" wire:confirm="{{ __('assets.files.delete_confirm') }}" aria-label="{{ __('app.delete') }} {{ $p->original_name }}"><x-icon name="trash" class="!w-4 !h-4" /></button>
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Documenti --}}
            <section class="card overflow-hidden" aria-labelledby="s-docs">
                <div class="flex flex-wrap items-center gap-2 border-b border-line-soft px-4 py-3">
                    <h2 id="s-docs" class="flex-1 text-base font-bold">{{ __('assets.files.documents') }} <span class="font-normal text-muted">{{ $docs->count() }}</span></h2>
                    @if($can['attach'])
                        <label class="btn btn-secondary btn-sm cursor-pointer">
                            <x-icon name="paperclip" class="!w-4 !h-4" />{{ __('assets.files.add_documents') }}
                            <input type="file" class="sr-only" wire:model="documents" multiple accept="{{ collect(config('manutrack.attachments.documents'))->map(fn ($e) => '.'.$e)->implode(',') }}">
                        </label>
                    @endif
                </div>
                <div wire:loading wire:target="documents" class="px-4 pt-3 text-sm text-muted">{{ __('assets.files.uploading') }}</div>
                @error('documents')<div class="px-4 pt-3"><span class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $message }}</span></div>@enderror
                @error('documents.*')<div class="px-4 pt-3"><span class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $message }}</span></div>@enderror
                @if($docs->isEmpty())
                    <p class="p-4 text-sm text-muted">{{ __('assets.files.no_documents') }}</p>
                @else
                    <ul>
                        @foreach($docs as $f)
                            <li class="flex items-center gap-3 border-b border-line-soft px-4 py-2.5 last:border-0" wire:key="d-{{ $f->id }}">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[10px] bg-surface-2 font-mono text-[11px] font-bold uppercase text-fg-2" aria-hidden="true">{{ $f->extension() }}</span>
                                <a href="{{ $f->downloadUrl() }}" class="min-w-0 flex-1">
                                    <span class="block truncate font-medium hover:underline">{{ $f->original_name }}</span>
                                    <span class="block text-xs text-muted">{{ $f->humanSize() }} · {{ $f->created_at->translatedFormat('j M Y') }}@if($f->user) · {{ $f->user->name }}@endif</span>
                                </a>
                                <a href="{{ $f->downloadUrl() }}" class="btn btn-ghost btn-icon btn-sm" aria-label="{{ __('assets.files.download') }} {{ $f->original_name }}"><x-icon name="download" class="!w-4 !h-4" /></a>
                                @if($can['manage'] || ($can['attach'] && $f->user_id === auth()->id()))
                                    <button type="button" class="btn btn-ghost btn-icon btn-sm text-ko" wire:click="deleteAttachment({{ $f->id }})" wire:confirm="{{ __('assets.files.delete_confirm') }}" aria-label="{{ __('app.delete') }} {{ $f->original_name }}"><x-icon name="trash" class="!w-4 !h-4" /></button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if($can['attach'])<p class="border-t border-line-soft px-4 py-2 text-xs text-muted">{{ __('assets.files.limits', ['max' => $maxMb, 'format' => strtoupper($photoFormat === 'none' ? 'originale' : $photoFormat)]) }}</p>@endif
            </section>

            {{-- Sottogruppi --}}
            @if($asset->children->isNotEmpty())
                <section class="card overflow-hidden" aria-labelledby="s-children">
                    <h2 id="s-children" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('assets.children') }} <span class="font-normal text-muted">{{ $asset->children->count() }}</span></h2>
                    <ul>
                        @foreach($asset->children as $c)
                            <li class="border-b border-line-soft last:border-0" wire:key="c-{{ $c->id }}">
                                <a href="{{ route('assets.show', $c) }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface-2">
                                    <x-asset-thumb :asset="$c" size="h-10 w-10" />
                                    <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $c->name }}</span><span class="block font-mono text-xs text-muted">{{ $c->code }}</span></span>
                                    <x-status :status="$c->status" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="flex flex-col gap-5">
            {{-- Dati tecnici --}}
            <section class="card p-4" aria-labelledby="s-data">
                <h2 id="s-data" class="mb-3 text-base font-bold">{{ __('assets.form.technical') }}</h2>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    @foreach(['manufacturer', 'model', 'serial_number', 'year'] as $field)
                        <dt class="text-muted">{{ __('assets.field.'.$field) }}</dt><dd @class(['font-mono' => $field === 'serial_number', 'break-words'])>{{ $asset->{$field} ?: '—' }}</dd>
                    @endforeach
                    <dt class="text-muted">{{ __('assets.field.installed_on') }}</dt><dd>{{ $asset->installed_on?->translatedFormat('j M Y') ?? '—' }}</dd>
                    <dt class="text-muted">{{ __('assets.field.warranty_until') }}</dt>
                    <dd>@if($asset->warranty_until){{ $asset->warranty_until->translatedFormat('j M Y') }} @if($warrantyExpired)<span class="text-xs text-muted">({{ __('assets.warranty_expired') }})</span>@else<x-status tone="ok" class="ml-1">{{ __('assets.warranty_active') }}</x-status>@endif @else — @endif</dd>
                    @if($can['costs'] && $asset->purchase_cost !== null)
                        <dt class="text-muted">{{ __('assets.field.purchase_cost') }}</dt><dd class="num">€ {{ number_format((float) $asset->purchase_cost, 2, ',', '.') }}</dd>
                    @endif
                </dl>
                @if($asset->notes)
                    <h3 class="mt-4 mb-1 text-sm font-semibold">{{ __('assets.field.notes') }}</h3>
                    <p class="whitespace-pre-line text-sm text-fg-2">{{ $asset->notes }}</p>
                @endif
            </section>

            {{-- Codice QR --}}
            <section class="card flex flex-col items-center gap-3 p-4 text-center" aria-labelledby="s-qr">
                <h2 id="s-qr" class="self-start text-base font-bold">{{ __('assets.qr.title') }}</h2>
                <div class="w-40 overflow-hidden rounded-lg bg-white p-1">{!! $qr !!}</div>
                <p class="text-xs text-muted">{{ __('assets.qr.explain') }}</p>
                <div class="flex flex-wrap justify-center gap-2">
                    <a href="{{ route('assets.labels', ['ids' => $asset->id]) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="printer" class="!w-4 !h-4" />{{ __('assets.qr.print') }}</a>
                    <a href="{{ route('assets.qr.svg', $asset) }}" class="btn btn-ghost btn-sm"><x-icon name="download" class="!w-4 !h-4" />SVG</a>
                </div>
                @if($can['manage'])
                    <button type="button" class="btn btn-ghost btn-sm text-muted" wire:click="regenerateQr" wire:confirm="{{ __('assets.qr.regenerate_confirm') }}"><x-icon name="refresh" class="!w-4 !h-4" />{{ __('assets.qr.regenerate') }}</button>
                @endif
            </section>

            @if($can['manage'])
                <section class="card p-4" aria-labelledby="s-danger">
                    <h2 id="s-danger" class="mb-2 text-base font-bold">{{ __('assets.danger.title') }}</h2>
                    <p class="mb-3 text-sm text-fg-2">{{ __('assets.danger.text') }}</p>
                    <button type="button" class="btn btn-danger btn-sm" wire:click="delete" wire:confirm="{{ __('assets.danger.confirm', ['code' => $asset->code]) }}"><x-icon name="trash" class="!w-4 !h-4" />{{ __('assets.danger.delete') }}</button>
                </section>
            @endif
        </aside>
    </div>
</div>
