@php
    $q = fn ($v) => \App\Models\Part::formatQty($v);
    $eur = fn ($v) => '€ '.number_format((float) $v, 2, ',', '.');
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <a href="{{ route('inventory.parts.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('inventory.title') }}</a>

    {{-- Intestazione --}}
    <section class="card grid gap-4 p-4 sm:p-5 md:grid-cols-[1fr_auto]">
        <div class="flex min-w-0 flex-col gap-2">
            <div class="font-mono text-sm text-muted">{{ $p->code }}</div>
            <h2 class="text-xl font-bold leading-tight sm:text-2xl">{{ $p->name }}</h2>
            <div class="flex flex-wrap items-center gap-2 text-sm text-fg-2">
                <x-stock-state :part="$p" />
                @unless($p->is_active)<x-status tone="stop" icon="pause-circle">{{ __('inventory.part.inactive') }}</x-status>@endunless
                @if($p->category)<span>{{ $p->category }}</span>@endif
                @if($p->manufacturer || $p->manufacturer_code)<span>· {{ $p->manufacturer }} <span class="font-mono">{{ $p->manufacturer_code }}</span></span>@endif
            </div>
            @if($p->description)<p class="whitespace-pre-line text-sm text-fg-2">{{ $p->description }}</p>@endif
        </div>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-1 self-start text-sm md:text-right">
            <dt class="text-muted">{{ __('inventory.field.stock') }}</dt><dd class="num text-2xl font-bold">{{ $q($p->stock_total) }} <span class="text-base font-normal text-muted">{{ $p->unit }}</span></dd>
            <dt class="text-muted">{{ __('inventory.field.min_stock') }}</dt><dd class="num">{{ (float) $p->min_stock > 0 ? $q($p->min_stock).' '.$p->unit : '—' }}</dd>
            @if($can['costs'])<dt class="text-muted">{{ __('inventory.field.unit_cost') }}</dt><dd class="num">{{ $eur($p->unit_cost) }}</dd>
            <dt class="text-muted">{{ __('inventory.field.value') }}</dt><dd class="num">{{ $eur((float) $p->stock_total * (float) $p->unit_cost) }}</dd>@endif
        </dl>
        <div class="flex flex-wrap gap-2 md:col-span-2">
            @if($can['move'])<button type="button" wire:click="start('issue')" @class(['btn btn-xl', 'btn-primary' => $action !== 'issue', 'btn-secondary ring-2 ring-[var(--mt-accent)]' => $action === 'issue'])><x-icon name="upload" />{{ __('inventory.moves.issue') }}</button>@endif
            @if($can['manage'])
                <button type="button" wire:click="start('receive')" @class(['btn btn-secondary', 'ring-2 ring-[var(--mt-accent)]' => $action === 'receive'])><x-icon name="download" />{{ __('inventory.moves.receive') }}</button>
                <button type="button" wire:click="start('adjust')" @class(['btn btn-secondary', 'ring-2 ring-[var(--mt-accent)]' => $action === 'adjust'])><x-icon name="pencil" />{{ __('inventory.moves.adjust') }}</button>
                <button type="button" wire:click="start('transfer')" @class(['btn btn-secondary', 'ring-2 ring-[var(--mt-accent)]' => $action === 'transfer'])><x-icon name="arrow-right" />{{ __('inventory.moves.transfer') }}</button>
                <a href="{{ route('inventory.parts.edit', $p) }}" class="btn btn-ghost ml-auto"><x-icon name="pencil" />{{ __('app.edit') }}</a>
            @endif
        </div>

        {{-- Modulo del movimento --}}
        @if($action !== '')
            @php $levels = $p->levels->keyBy('warehouse_id'); @endphp
            <form wire:submit="submit" class="flex flex-col gap-4 rounded-[var(--radius-md)] border border-line bg-surface-2/40 p-4 md:col-span-2" aria-labelledby="mv-title">
                <h3 id="mv-title" class="font-bold">{{ __('inventory.moves.title.'.$action) }}</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="field">
                        <label for="mv-wh" class="field-label">{{ $action === 'transfer' ? __('inventory.moves.from') : __('inventory.field.warehouse') }}</label>
                        <select id="mv-wh" wire:model.live="move.warehouse_id" class="input">
                            @foreach($warehouses as $w)
                                @if($w->is_active || $levels->has($w->id))
                                    <option value="{{ $w->id }}">{{ $w->name }} · {{ $q($levels[$w->id]->quantity ?? 0) }} {{ $p->unit }}@if($levels[$w->id]?->bin ?? null) · {{ $levels[$w->id]->bin }}@endif</option>
                                @endif
                            @endforeach
                        </select>
                        @error('move.warehouse_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    @if($action === 'transfer')
                        <div class="field">
                            <label for="mv-to" class="field-label">{{ __('inventory.moves.to') }}</label>
                            <select id="mv-to" wire:model="move.to_warehouse_id" class="input"><option value="">—</option>@foreach($warehouses->where('is_active', true) as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
                            @error('move.to_warehouse_id')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    @endif
                    <div class="field">
                        <label for="mv-qty" class="field-label">{{ $action === 'adjust' ? __('inventory.moves.counted') : __('inventory.field.quantity') }}</label>
                        <div class="relative"><input id="mv-qty" type="text" inputmode="decimal" wire:model="move.quantity" class="input pr-14 num text-lg" autocomplete="off"><span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm text-muted">{{ $p->unit }}</span></div>
                        @error('move.quantity')<span class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $message }}</span>@enderror
                    </div>
                    @if($action === 'issue')
                        <div class="field">
                            <label for="mv-wo" class="field-label">{{ __('inventory.moves.for_order') }}</label>
                            <input id="mv-wo" list="mv-wos" wire:model="move.work_order" class="input font-mono" placeholder="OdL-{{ now()->year }}-…">
                            <datalist id="mv-wos">@foreach($activeOrders as $o)<option value="{{ $o->number }}">{{ $o->asset?->code }} · {{ \Illuminate\Support\Str::limit($o->title, 50) }}</option>@endforeach</datalist>
                            @error('move.work_order')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    @elseif($action === 'receive')
                        @if($can['costs'])<x-form.input name="move.unit_cost" :label="__('inventory.field.unit_cost_eur')" wire:model="move.unit_cost" inputmode="decimal" :hint="__('inventory.moves.cost_hint')" />@endif
                        <div class="field">
                            <label for="mv-sup" class="field-label">{{ __('inventory.field.supplier') }}</label>
                            <select id="mv-sup" wire:model="move.supplier_id" class="input"><option value="">—</option>@foreach($suppliers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                        </div>
                        <x-form.input name="move.reference" :label="__('inventory.field.reference')" wire:model="move.reference" :placeholder="__('inventory.moves.reference_placeholder')" />
                    @endif
                </div>
                <x-form.input name="move.note" :label="$action === 'adjust' ? __('inventory.moves.reason') : __('maintenance.request.note_optional')" wire:model="move.note" />
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><x-icon name="check" />{{ __('maintenance.actions.confirm') }}</button>
                    <button type="button" class="btn btn-ghost" wire:click="$set('action', '')">{{ __('app.cancel') }}</button>
                </div>
            </form>
        @endif
    </section>

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="flex min-w-0 flex-col gap-5">
            {{-- Giacenze per magazzino --}}
            <section class="card overflow-hidden" aria-labelledby="ps-levels">
                <h2 id="ps-levels" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('inventory.part.levels') }}</h2>
                <ul>
                    @forelse($p->levels->sortBy(fn ($l) => $l->warehouse->name) as $l)
                        <li class="flex flex-wrap items-center gap-3 border-b border-line-soft px-4 py-2.5 last:border-0" wire:key="lv-{{ $l->id }}">
                            <x-icon name="box" class="text-muted" />
                            <span class="min-w-0 flex-1"><span class="font-medium">{{ $l->warehouse->name }}</span>
                                @if($can['manage'])
                                    <form wire:submit="saveBin({{ $l->id }})" class="mt-1 flex items-center gap-1">
                                        <input type="text" wire:model="bins.{{ $l->id }}" placeholder="{{ $l->bin ?? __('inventory.field.bin') }}" value="{{ $l->bin }}" class="input !min-h-8 w-32 py-0 font-mono text-xs" aria-label="{{ __('inventory.field.bin') }} {{ $l->warehouse->name }}">
                                        @if(isset($bins[$l->id]))<button type="submit" class="btn btn-ghost btn-sm">{{ __('app.save') }}</button>@endif
                                    </form>
                                @elseif($l->bin)<span class="block font-mono text-xs text-muted">{{ $l->bin }}</span>@endif
                            </span>
                            <span class="num text-lg font-semibold">{{ $q($l->quantity) }} <span class="text-sm font-normal text-muted">{{ $p->unit }}</span></span>
                        </li>
                    @empty
                        <li class="px-4 py-4 text-sm text-muted">{{ __('inventory.part.no_stock') }}</li>
                    @endforelse
                </ul>
            </section>

            {{-- Movimenti --}}
            <section class="card overflow-hidden" aria-labelledby="ps-moves">
                <div class="flex items-center gap-2 border-b border-line-soft px-4 py-3">
                    <h2 id="ps-moves" class="flex-1 text-base font-bold">{{ __('inventory.movements.title') }}</h2>
                    <a href="{{ route('inventory.movements', ['ricambio' => $p->id]) }}" class="btn btn-ghost btn-sm">{{ __('app.home.see_all') }}</a>
                </div>
                <ul>
                    @forelse($movements as $m)
                        @php $in = (float) $m->quantity > 0; @endphp
                        <li class="flex items-start gap-3 border-b border-line-soft px-4 py-2.5 text-sm last:border-0">
                            <x-icon :name="$m->type->icon()" @class(['mt-0.5 !w-4 !h-4', 'text-ok' => $in, 'text-warn' => ! $in]) />
                            <span class="min-w-0 flex-1">
                                <span class="font-medium">{{ $m->type->label() }}</span>
                                <span class="text-muted">· {{ $m->warehouse->name }}@if($m->workOrder) · <a href="{{ route('work-orders.show', $m->workOrder) }}" class="underline">{{ $m->workOrder->number }}</a>@endif @if($m->supplier) · {{ $m->supplier->name }}@endif @if($m->reference) · {{ $m->reference }}@endif</span>
                                <span class="block text-xs text-muted">{{ $m->created_at->translatedFormat('j M Y H:i') }} · {{ $m->user?->name ?? '—' }}@if($m->note) · {{ $m->note }}@endif</span>
                            </span>
                            <span class="text-right">
                                <span @class(['num block font-semibold', 'text-ok' => $in])>{{ $in ? '+' : '−' }}{{ $q(abs((float) $m->quantity)) }}</span>
                                <span class="num block text-xs text-muted">{{ __('inventory.movements.balance', ['qty' => $q($m->balance_after)]) }}</span>
                            </span>
                        </li>
                    @empty
                        <li class="px-4 py-4 text-sm text-muted">{{ __('inventory.movements.empty') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside class="flex flex-col gap-5">
            {{-- Fornitori --}}
            <section class="card p-4" aria-labelledby="ps-sup">
                <div class="mb-2 flex items-center gap-2">
                    <h2 id="ps-sup" class="flex-1 text-base font-bold">{{ __('inventory.suppliers.title') }}</h2>
                    @if($can['manage'] && $editingSupplier === null)<button type="button" class="btn btn-ghost btn-sm" wire:click="editSupplier"><x-icon name="plus" class="!w-4 !h-4" />{{ __('inventory.part.add_supplier') }}</button>@endif
                </div>
                @if($editingSupplier !== null)
                    <form wire:submit="saveSupplier" class="mb-3 flex flex-col gap-3">
                        <div class="field">
                            <label for="sf-sup" class="field-label">{{ __('inventory.field.supplier') }}</label>
                            <select id="sf-sup" wire:model="supplierForm.supplier_id" class="input" @disabled($editingSupplier)><option value="">—</option>@foreach($suppliers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                            @error('supplierForm.supplier_id')<span class="field-error">{{ $message }}</span>@enderror
                            @if($suppliers->isEmpty())<span class="field-hint">{!! __('inventory.part.no_suppliers', ['url' => e(route('inventory.suppliers'))]) !!}</span>@endif
                        </div>
                        <x-form.input name="supplierForm.supplier_code" :label="__('inventory.field.supplier_code')" wire:model="supplierForm.supplier_code" class="font-mono" />
                        <div class="grid grid-cols-2 gap-3">
                            @if($can['costs'])<x-form.input name="supplierForm.price" :label="__('inventory.field.price')" wire:model="supplierForm.price" inputmode="decimal" />@endif
                            <x-form.input name="supplierForm.lead_time_days" :label="__('inventory.field.lead_time_days')" wire:model="supplierForm.lead_time_days" inputmode="numeric" />
                        </div>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="supplierForm.is_preferred" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('inventory.field.is_preferred') }}</label>
                        <div class="flex gap-2"><button type="submit" class="btn btn-primary btn-sm">{{ __('app.save') }}</button><button type="button" class="btn btn-ghost btn-sm" wire:click="$set('editingSupplier', null)">{{ __('app.cancel') }}</button></div>
                    </form>
                @endif
                <ul class="flex flex-col gap-2 text-sm">
                    @forelse($p->suppliers as $s)
                        <li class="rounded-[var(--radius-md)] border border-line p-2.5">
                            <div class="flex items-center gap-2">
                                <span class="flex-1 font-medium">{{ $s->name }}</span>
                                @if($s->pivot->is_preferred)<x-status tone="ok" icon="star">{{ __('inventory.part.preferred') }}</x-status>@endif
                            </div>
                            <div class="text-muted">
                                @if($s->pivot->supplier_code)<span class="font-mono">{{ $s->pivot->supplier_code }}</span>@endif
                                @if($can['costs'] && $s->pivot->price !== null) · {{ $eur($s->pivot->price) }}@endif
                                @if($s->pivot->lead_time_days !== null) · {{ trans_choice('inventory.part.lead_days', $s->pivot->lead_time_days, ['days' => $s->pivot->lead_time_days]) }}@endif
                            </div>
                            @if($can['manage'])
                                <div class="mt-1 flex gap-1">
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="editSupplier({{ $s->pivot->id }})">{{ __('app.edit') }}</button>
                                    <button type="button" class="btn btn-ghost btn-sm text-ko" wire:click="removeSupplier({{ $s->id }})" wire:confirm="{{ __('inventory.part.remove_supplier_confirm') }}">{{ __('app.delete') }}</button>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('inventory.part.no_supplier') }}</li>
                    @endforelse
                </ul>
            </section>

            {{-- Distinta: macchine in cui è montato --}}
            <section class="card p-4" aria-labelledby="ps-bom">
                <h2 id="ps-bom" class="mb-2 text-base font-bold">{{ __('inventory.part.used_in') }}</h2>
                <ul class="flex flex-col gap-1.5 text-sm">
                    @forelse($p->assets as $a)
                        <li class="flex items-center gap-2">
                            <a href="{{ route('assets.show', $a) }}" class="min-w-0 flex-1 truncate underline"><span class="font-mono">{{ $a->code }}</span> · {{ $a->name }}</a>
                            <span class="num text-muted">× {{ $q($a->pivot->quantity) }}</span>
                            @if($can['bom'])<button type="button" class="btn btn-ghost btn-icon btn-sm text-muted" wire:click="removeAsset({{ $a->id }})" aria-label="{{ __('app.delete') }} {{ $a->code }}"><x-icon name="x" class="!w-4 !h-4" /></button>@endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('inventory.part.no_assets') }}</li>
                    @endforelse
                </ul>
                @if($can['bom'])
                    <form wire:submit="addAsset" class="mt-3 flex gap-2">
                        <select wire:model="assetToAdd" class="input min-w-0 flex-1" aria-label="{{ __('maintenance.field.asset') }}"><option value="">{{ __('inventory.part.add_asset') }}</option>@foreach($assetOptions as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>@endforeach</select>
                        <input type="text" inputmode="decimal" wire:model="assetQty" class="input w-16" aria-label="{{ __('inventory.field.quantity') }}">
                        <button type="submit" class="btn btn-secondary btn-icon" aria-label="{{ __('inventory.part.add_asset') }}"><x-icon name="plus" /></button>
                    </form>
                    @error('assetToAdd')<span class="field-error">{{ $message }}</span>@enderror
                    @error('assetQty')<span class="field-error">{{ $message }}</span>@enderror
                @endif
            </section>
        </aside>
    </div>
</div>
