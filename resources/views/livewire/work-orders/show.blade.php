@php
    use App\Enums\WorkOrderStatus as S;
    $fmtMin = fn (int $m) => $m >= 60 ? intdiv($m, 60).' h '.str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT).' min' : $m.' min';
    $label = fn (S $to) => match (true) {
        $to === S::InProgress && $o->status === S::Completed => __('maintenance.actions.reopen'),
        $to === S::InProgress && $o->status === S::Waiting => __('maintenance.actions.resume'),
        default => __('maintenance.actions.'.$to->value),
    };
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <a href="{{ route('work-orders.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('maintenance.order.title') }}</a>

    {{-- Intestazione e azioni --}}
    <section class="card flex flex-col gap-4 p-4 sm:p-5">
        <div class="flex flex-wrap items-center gap-2">
            <x-status :status="$o->status" />
            @if($o->waiting_reason)<span class="text-sm text-fg-2">{{ $o->waiting_reason->label() }}</span>@endif
            <x-priority :priority="$o->priority" />
            <span class="inline-flex items-center gap-1 text-sm text-fg-2"><x-icon :name="$o->type->icon()" class="!w-4 !h-4" />{{ $o->type->label() }}</span>
            @if($o->reopened_count)<x-status tone="warn" icon="refresh">{{ trans_choice('maintenance.order.reopened', $o->reopened_count, ['count' => $o->reopened_count]) }}</x-status>@endif
        </div>
        <div>
            <div class="font-mono text-sm text-muted">{{ $o->number }}</div>
            <h2 class="text-xl font-bold leading-tight sm:text-2xl">{{ $o->title }}</h2>
        </div>
        <dl class="grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-[auto_1fr]">
            @if($o->asset)
                <dt class="text-muted">{{ __('maintenance.field.asset') }}</dt>
                <dd><a href="{{ route('assets.show', $o->asset) }}" class="font-medium underline">{{ $o->asset->code }} · {{ $o->asset->name }}</a> <x-status :status="$o->asset->status" class="ml-1" />@if($o->asset->location)<span class="block text-muted">{{ $o->asset->location->fullName() }}</span>@endif</dd>
            @elseif($o->location)
                <dt class="text-muted">{{ __('assets.field.location_id') }}</dt><dd>{{ $o->location->fullName() }}</dd>
            @endif
            @if($o->due_at)<dt class="text-muted">{{ __('maintenance.field.due_at') }}</dt><dd @class(['font-semibold text-ko' => $o->isOverdue()])>{{ $o->due_at->translatedFormat('j M Y') }}@if($o->isOverdue()) · {{ __('maintenance.order.overdue') }}@endif</dd>@endif
            @if($o->planned_start_at)<dt class="text-muted">{{ __('maintenance.field.planned_start_at') }}</dt><dd>{{ $o->planned_start_at->translatedFormat('j M Y, H:i') }}</dd>@endif
            <dt class="text-muted">{{ __('maintenance.order.created_by') }}</dt><dd>{{ $o->creator?->name ?? ($o->maintenance_plan_id ? __('preventive.plan.system') : '—') }} · {{ $o->created_at->translatedFormat('j M Y, H:i') }}</dd>
            @if($o->plan)
                <dt class="text-muted">{{ __('preventive.plan.from_plan') }}</dt>
                <dd>@can('preventive.view')<a href="{{ route('preventive.show', $o->plan) }}" class="underline">{{ $o->plan->title }}</a>@else{{ $o->plan->title }}@endcan · {{ $o->plan->frequencyLabel() }}@if($o->plan_due_on) · {{ __('preventive.field.planned_due') }} {{ $o->plan_due_on->translatedFormat('j M Y') }}@endif</dd>
            @endif
            @if($o->request)
                <dt class="text-muted">{{ __('maintenance.order.from_request') }}</dt>
                <dd><a href="{{ route('requests.show', $o->request) }}" class="underline">{{ $o->request->number }}</a> · {{ $o->request->user?->name }}@if($o->request->machine_stopped) · <span class="text-ko">{{ __('maintenance.request.stopped') }}</span>@endif</dd>
            @endif
        </dl>
        @if($o->description)<p class="whitespace-pre-line text-fg-2">{{ $o->description }}</p>@endif

        <div class="flex flex-wrap gap-2">
            @if($can['take'])
                <button type="button" class="btn btn-primary btn-xl" wire:click="take"><x-icon name="user" />{{ __('maintenance.actions.take') }}</button>
            @endif
            @foreach($transitions as $to)
                @php $primary = in_array($to, [S::InProgress, S::Completed, S::Closed], true) && ! ($to === S::InProgress && $o->status === S::Completed); @endphp
                <button type="button" wire:click="move('{{ $to->value }}')" wire:loading.attr="disabled"
                        @class(['btn', 'btn-xl btn-primary' => $primary, 'btn-secondary' => ! $primary && $to !== S::Cancelled, 'btn-ghost text-ko' => $to === S::Cancelled, 'ring-2 ring-[var(--mt-accent)]' => $pending === $to->value])>
                    <x-icon :name="$to === S::InProgress && $o->status === S::Completed ? 'refresh' : $to->icon()" />{{ $label($to) }}
                </button>
            @endforeach
            <span class="ml-auto flex flex-wrap gap-2">
                @if($can['correct'])<button type="button" class="btn btn-ghost" wire:click="startCorrection"><x-icon name="shield" />{{ __('maintenance.correction.button') }}</button>@endif
                @if($can['update'])<button type="button" class="btn btn-ghost" wire:click="editDetails"><x-icon name="pencil" />{{ __('app.edit') }}</button>@endif
            </span>
        </div>

        {{-- Correggi stato: solo amministratore, fuori dal ciclo normale --}}
        @if($correcting)
            <form wire:submit="saveCorrection" class="flex flex-col gap-4 rounded-[var(--radius-md)] border border-[color-mix(in_oklab,var(--mt-warn)_55%,transparent)] p-4" aria-labelledby="corr-title">
                <div>
                    <h3 id="corr-title" class="font-bold">{{ __('maintenance.correction.title') }}</h3>
                    <p class="text-sm text-fg-2">{{ __('maintenance.correction.intro') }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="field">
                        <label for="corr-to" class="field-label">{{ __('maintenance.correction.to') }}</label>
                        <select id="corr-to" wire:model.live="correction.to" class="input">
                            <option value="">—</option>
                            @foreach($allStatuses as $st)<option value="{{ $st->value }}">{{ $st->label() }}@if($st === $o->status) ({{ __('maintenance.correction.current') }})@endif</option>@endforeach
                        </select>
                        @error('correction.to')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    @if(in_array($correction['to'], ['completato', 'chiuso'], true))
                        <x-form.input name="correction.completed_at" type="datetime-local" :label="__('maintenance.correction.completed_at')" wire:model="correction.completed_at" />
                    @elseif($correction['to'] === 'in_attesa')
                        <div class="field">
                            <label for="corr-wait" class="field-label">{{ __('maintenance.field.waiting_reason') }}</label>
                            <select id="corr-wait" wire:model="correction.waiting_reason" class="input">@foreach($reasons as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach</select>
                        </div>
                    @endif
                </div>
                <div class="field">
                    <label for="corr-reason" class="field-label">{{ __('maintenance.correction.reason') }}</label>
                    <textarea id="corr-reason" wire:model="correction.reason" rows="2" class="input py-2.5" placeholder="{{ __('maintenance.correction.reason_placeholder') }}"></textarea>
                    @error('correction.reason')<span class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $message }}</span>@enderror
                </div>
                @if($linkedDowntimes->isNotEmpty())
                    <fieldset>
                        <legend class="field-label mb-1">{{ __('maintenance.correction.downtime') }}</legend>
                        <ul class="mb-2 text-sm text-muted">
                            @foreach($linkedDowntimes as $d)<li>{{ $d->started_at->translatedFormat('j M H:i') }} → {{ $d->ended_at?->translatedFormat('j M H:i') ?? __('maintenance.correction.still_open') }}</li>@endforeach
                        </ul>
                        <div class="grid gap-2 sm:grid-cols-4">
                            @foreach(['' => 'auto', 'keep' => 'keep', 'close' => 'close', 'reopen' => 'reopen'] as $value => $key)
                                <label class="choice !items-center !p-2.5"><input type="radio" value="{{ $value }}" wire:model="correction.downtime" class="sr-only"><span class="text-sm font-medium">{{ __('maintenance.correction.downtime_'.$key) }}</span></label>
                            @endforeach
                        </div>
                        <span class="field-hint">{{ __('maintenance.correction.downtime_hint') }}</span>
                    </fieldset>
                @endif
                @error('correction.completed_at')<span class="field-error">{{ $message }}</span>@enderror
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('maintenance.correction.save') }}</button>
                    <button type="button" class="btn btn-ghost" wire:click="$set('correcting', false)">{{ __('app.cancel') }}</button>
                </div>
            </form>
        @endif
        @error('transition.status')<div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ $message }}</span></div>@enderror
        @error('transition.checklist')<div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ $message }}</span></div>@enderror

        {{-- Modulo del passaggio di stato --}}
        @if($pending !== '')
            <form wire:submit="move('{{ $pending }}')" class="flex flex-col gap-4 rounded-[var(--radius-md)] border border-line bg-surface-2/40 p-4">
                @if($pending === 'in_attesa')
                    <fieldset>
                        <legend class="field-label mb-2">{{ __('maintenance.field.waiting_reason') }}</legend>
                        <div class="grid gap-2 sm:grid-cols-3">
                            @foreach($reasons as $r)<label class="choice !items-center"><input type="radio" value="{{ $r->value }}" wire:model="transition.waiting_reason" class="sr-only"><span class="font-medium">{{ $r->label() }}</span></label>@endforeach
                        </div>
                    </fieldset>
                    <x-form.input name="transition.note" :label="__('maintenance.request.note_optional')" wire:model="transition.note" />
                @elseif($pending === 'completato')
                    <div class="field">
                        <label for="t-notes" class="field-label">{{ __('maintenance.field.completion_notes') }}</label>
                        <textarea id="t-notes" wire:model="transition.completion_notes" rows="3" class="input py-2.5" placeholder="{{ __('maintenance.order.completion_placeholder') }}"></textarea>
                        @error('transition.completion_notes')<span class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $message }}</span>@enderror
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach($kinds as $k)
                            <div class="field">
                                <label for="t-{{ $k->value }}" class="field-label">{{ $k->label() }}</label>
                                <select id="t-{{ $k->value }}" wire:model="transition.failure_{{ ['problema' => 'problem', 'causa' => 'cause', 'rimedio' => 'remedy'][$k->value] }}_id" class="input">
                                    <option value="">—</option>
                                    @foreach($codes[$k->value] ?? [] as $c)<option value="{{ $c->id }}">{{ $c->display() }}</option>@endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                    @if($o->asset && ! in_array($o->asset->status->value, ['operativa', 'dismessa'], true))
                        <label class="flex items-start gap-3"><input type="checkbox" wire:model="transition.asset_operational" class="mt-0.5 h-5 w-5 accent-[var(--mt-accent)]"><span><span class="font-medium">{{ __('maintenance.order.asset_back', ['code' => $o->asset->code]) }}</span><span class="block text-sm text-muted">{{ __('maintenance.order.asset_back_hint') }}</span></span></label>
                    @endif
                @else
                    <x-form.input name="transition.note" :label="$pending === 'annullato' ? __('maintenance.order.cancel_reason') : __('maintenance.order.reopen_reason')" wire:model="transition.note" required />
                @endif
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><x-icon name="check" />{{ __('maintenance.actions.confirm') }}</button>
                    <button type="button" class="btn btn-ghost" wire:click="cancelPending">{{ __('app.cancel') }}</button>
                </div>
            </form>
        @endif

        @if($editing)
            <form wire:submit="saveDetails" class="flex flex-col gap-4 rounded-[var(--radius-md)] border border-line p-4">
                <x-form.input name="details.title" :label="__('maintenance.field.title')" wire:model="details.title" />
                <div class="field"><label for="d-desc" class="field-label">{{ __('maintenance.field.description') }}</label><textarea id="d-desc" wire:model="details.description" rows="3" class="input py-2.5"></textarea></div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="field"><label for="d-prio" class="field-label">{{ __('maintenance.field.priority') }}</label><select id="d-prio" wire:model="details.priority" class="input">@foreach($priorities as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select></div>
                    <x-form.input name="details.due_at" type="date" :label="__('maintenance.field.due_at')" wire:model="details.due_at" />
                    <x-form.input name="details.planned_start_at" type="datetime-local" :label="__('maintenance.field.planned_start_at')" wire:model="details.planned_start_at" />
                </div>
                <div class="flex gap-2"><button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('app.save') }}</button><button type="button" class="btn btn-ghost" wire:click="$set('editing', false)">{{ __('app.cancel') }}</button></div>
            </form>
        @endif
    </section>

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_21rem]">
        <div class="flex min-w-0 flex-col gap-5">
            {{-- Checklist --}}
            @php $done = $o->checklist->filter->isDone()->count(); @endphp
            <section class="card overflow-hidden" aria-labelledby="w-check">
                <div class="flex items-center gap-2 border-b border-line-soft px-4 py-3">
                    <h2 id="w-check" class="flex-1 text-base font-bold">{{ __('maintenance.checklist.title') }}</h2>
                    @if($o->checklist->isNotEmpty())<span class="num text-sm text-muted">{{ $done }}/{{ $o->checklist->count() }}</span>@endif
                </div>
                @if($o->checklist->isNotEmpty())
                    <div class="h-1 bg-surface-2"><div class="h-1 bg-[var(--mt-ok)] transition-all" style="width: {{ $o->checklist->count() ? round($done / $o->checklist->count() * 100) : 0 }}%"></div></div>
                @endif
                <ul>
                    @forelse($o->checklist as $item)
                        <li class="flex items-start gap-3 border-b border-line-soft px-4 py-2.5 last:border-0" wire:key="ci-{{ $item->id }}">
                            @if($item->kind === 'valore' && ! $item->isDone() && $can['execute'])
                                <form wire:submit="saveValue({{ $item->id }})" class="flex w-full flex-wrap items-center gap-2">
                                    <span class="min-w-0 flex-1 font-medium">{{ $item->label }}@if($item->required)<span class="text-ko" title="{{ __('maintenance.checklist.required') }}"> *</span>@endif</span>
                                    <span class="relative"><input type="text" inputmode="decimal" wire:model="values.{{ $item->id }}" class="input w-36 pr-12" aria-label="{{ $item->label }}"><span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm text-muted">{{ $item->unit }}</span></span>
                                    <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="check" class="!w-4 !h-4" />{{ __('app.save') }}</button>
                                    @error('values.'.$item->id)<span class="field-error w-full">{{ $message }}</span>@enderror
                                </form>
                            @else
                                <label @class(['flex min-h-10 flex-1 items-start gap-3', 'cursor-pointer' => $can['execute']])>
                                    <input type="checkbox" @checked($item->isDone()) @disabled(! $can['execute']) wire:click="toggleItem({{ $item->id }})" class="mt-0.5 h-6 w-6 shrink-0 accent-[var(--mt-ok)]">
                                    <span class="min-w-0">
                                        <span @class(['font-medium', 'text-muted line-through' => $item->isDone() && $item->kind === 'spunta'])>{{ $item->label }}</span>@if($item->required && ! $item->isDone())<span class="text-ko" title="{{ __('maintenance.checklist.required') }}"> *</span>@endif
                                        @if($item->value !== null)<span class="ml-1 font-mono font-semibold">{{ $item->value }} {{ $item->unit }}</span>@endif
                                        @if($item->isDone())<span class="block text-xs text-muted">{{ $item->doneBy?->name }} · {{ $item->done_at->translatedFormat('j M H:i') }}</span>@endif
                                    </span>
                                </label>
                            @endif
                            @if($can['assign'] && ! $item->isDone())<button type="button" class="btn btn-ghost btn-icon btn-sm text-muted" wire:click="removeItem({{ $item->id }})" aria-label="{{ __('app.delete') }} {{ $item->label }}"><x-icon name="x" class="!w-4 !h-4" /></button>@endif
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-muted">{{ __('maintenance.checklist.empty') }}</li>
                    @endforelse
                </ul>
                @if($can['execute'])
                    <div class="flex flex-wrap gap-2 border-t border-line-soft p-3">
                        <form wire:submit="addItem" class="flex min-w-[14rem] flex-1 gap-2">
                            <input type="text" wire:model="newItem" class="input" placeholder="{{ __('maintenance.checklist.add_placeholder') }}" aria-label="{{ __('maintenance.checklist.item') }}">
                            <button type="submit" class="btn btn-secondary btn-icon" aria-label="{{ __('maintenance.checklist.add') }}"><x-icon name="plus" /></button>
                        </form>
                        @if($templates->isNotEmpty())
                            <form wire:submit="applyTemplate" class="flex gap-2">
                                <select wire:model="template" class="input" aria-label="{{ __('maintenance.field.checklist') }}"><option value="">{{ __('maintenance.checklist.from_template') }}</option>@foreach($templates as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                                <button type="submit" class="btn btn-secondary">{{ __('maintenance.checklist.apply') }}</button>
                            </form>
                        @endif
                    </div>
                @endif
            </section>

            {{-- Tempi --}}
            <section class="card overflow-hidden" aria-labelledby="w-time">
                <div class="flex flex-wrap items-center gap-2 border-b border-line-soft px-4 py-3">
                    <h2 id="w-time" class="flex-1 text-base font-bold">{{ __('maintenance.time.title') }} <span class="font-normal text-muted">{{ $fmtMin($laborMinutes) }}</span></h2>
                    @if($can['execute'])
                        @if($myTimer)
                            <button type="button" class="btn btn-danger" wire:click="stopTimer"><x-icon name="pause-circle" />{{ __('maintenance.time.stop') }} <span class="num" x-data="{ s: {{ (int) $myTimer->started_at->diffInSeconds(now()) }} }" x-init="setInterval(() => s++, 1000)" x-text="Math.floor(s / 3600) + ':' + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0')"></span></button>
                        @elseif($o->status === S::InProgress)
                            <button type="button" class="btn btn-primary" wire:click="startTimer"><x-icon name="clock" />{{ __('maintenance.time.start') }}</button>
                        @endif
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="$toggle('addingTime')"><x-icon name="plus" class="!w-4 !h-4" />{{ __('maintenance.time.add') }}</button>
                    @endif
                </div>
                @if($addingTime)
                    <form wire:submit="addTime" class="grid gap-3 border-b border-line-soft p-4 sm:grid-cols-[8rem_10rem_1fr_auto] sm:items-end">
                        <x-form.input name="manualTime.minutes" :label="__('maintenance.time.minutes')" wire:model="manualTime.minutes" inputmode="numeric" />
                        <x-form.input name="manualTime.date" type="date" :label="__('maintenance.time.date')" wire:model="manualTime.date" />
                        <x-form.input name="manualTime.note" :label="__('maintenance.request.note_optional')" wire:model="manualTime.note" />
                        <button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('app.save') }}</button>
                    </form>
                @endif
                <ul>
                    @forelse($o->timeEntries as $t)
                        <li class="flex items-center gap-3 border-b border-line-soft px-4 py-2 text-sm last:border-0" wire:key="t-{{ $t->id }}">
                            <x-icon :name="$t->isRunning() ? 'clock' : 'check'" @class(['!w-4 !h-4', 'text-ok' => $t->isRunning(), 'text-muted' => ! $t->isRunning()]) />
                            <span class="min-w-0 flex-1"><span class="font-medium">{{ $t->user?->name ?? '—' }}</span> <span class="text-muted">· {{ $t->started_at->translatedFormat('j M H:i') }}@if($t->note) · {{ $t->note }}@endif</span></span>
                            <span class="num font-semibold">{{ $t->isRunning() ? __('maintenance.time.running') : $fmtMin($t->minutes) }}</span>
                            @if(! $t->isRunning() && ($t->user_id === auth()->id() || $can['assign']))<button type="button" class="btn btn-ghost btn-icon btn-sm text-muted" wire:click="deleteTime({{ $t->id }})" wire:confirm="{{ __('maintenance.time.delete_confirm') }}" aria-label="{{ __('app.delete') }}"><x-icon name="trash" class="!w-4 !h-4" /></button>@endif
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-muted">{{ __('maintenance.time.empty') }}</li>
                    @endforelse
                </ul>
            </section>

            {{-- Ricambi --}}
            @if(auth()->user()->can('inventory.view') || $o->stockMovements->isNotEmpty())
                <livewire:work-orders.parts :work-order="$o" :key="'parts-'.$o->id" />
            @endif

            {{-- Foto --}}
            <section class="card overflow-hidden" aria-labelledby="w-photos">
                <div class="flex items-center gap-2 border-b border-line-soft px-4 py-3">
                    <h2 id="w-photos" class="flex-1 text-base font-bold">{{ __('assets.files.photos') }} <span class="font-normal text-muted">{{ $gallery->count() + $requestPhotos->count() }}</span></h2>
                    @if($can['execute'])
                        <label class="btn btn-secondary btn-sm cursor-pointer"><x-icon name="camera" class="!w-4 !h-4" />{{ __('assets.files.add_photos') }}<input type="file" class="sr-only" x-data x-photo-upload="photos" accept="image/*" multiple></label>
                    @endif
                </div>
                <div wire:loading wire:target="photos" class="px-4 pt-3 text-sm text-muted">{{ __('assets.files.uploading') }}</div>
                @error('photos')<div class="px-4 pt-3"><span class="field-error">{{ $message }}</span></div>@enderror
                @if($gallery->isEmpty() && $requestPhotos->isEmpty())
                    <p class="p-4 text-sm text-muted">{{ __('maintenance.order.no_photos') }}</p>
                @else
                    <ul class="grid grid-cols-3 gap-2 p-4 sm:grid-cols-4">
                        @foreach($requestPhotos->concat($gallery) as $p)
                            <li class="relative overflow-hidden rounded-[var(--radius-md)] bg-surface-2" wire:key="ph-{{ $p->id }}">
                                @if($p->isPhoto())
                                    <a href="{{ $p->url() }}" target="_blank" rel="noopener"><img src="{{ $p->url('thumb') }}" alt="{{ $p->original_name }}" loading="lazy" class="aspect-square w-full object-cover"></a>
                                @else
                                    <a href="{{ $p->downloadUrl() }}" class="flex aspect-square flex-col items-center justify-center gap-1 p-2 text-center text-xs"><x-icon name="file-text" />{{ $p->original_name }}</a>
                                @endif
                                @if($p->attachable_type === \App\Models\WorkRequest::class)<span class="absolute left-1 top-1 rounded-full bg-black/60 px-2 py-0.5 text-[11px] text-white">{{ __('maintenance.order.from_request_short') }}</span>@endif
                                @if($p->attachable_type === \App\Models\WorkOrder::class && ($p->user_id === auth()->id() || $can['assign']))
                                    <button type="button" class="btn btn-icon btn-sm absolute bottom-1 right-1 !min-h-8 !w-8 bg-black/50 text-white" wire:click="deletePhoto({{ $p->id }})" wire:confirm="{{ __('assets.files.delete_confirm') }}" aria-label="{{ __('app.delete') }}"><x-icon name="trash" class="!w-4 !h-4" /></button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Commenti e storico --}}
            <section class="card overflow-hidden" aria-labelledby="w-log">
                <h2 id="w-log" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('maintenance.comments.title') }}</h2>
                <ol class="flex flex-col gap-3 p-4">
                    @foreach($timeline as $e)
                        @if($e['kind'] === 'status')
                            @php $s = $e['item']; @endphp
                            <li class="flex items-start gap-2 text-sm text-fg-2">
                                <x-icon :name="$s->to->icon()" class="mt-0.5 !w-4 !h-4 text-muted" />
                                <span>@if($s->is_correction)<x-status tone="warn" icon="shield" class="mr-1">{{ __('maintenance.correction.badge') }}</x-status>@endif{!! __('maintenance.comments.status', ['name' => '<strong>'.e($s->user?->name ?? ($o->maintenance_plan_id ? __('preventive.plan.system_short') : '—')).'</strong>', 'status' => '<strong>'.e($s->to->label()).'</strong>']) !!}@if($s->note) · {{ $s->note }}@endif <span class="text-muted">· {{ $s->created_at->translatedFormat('j M H:i') }}</span></span>
                            </li>
                        @else
                            @php $c = $e['item']; @endphp
                            <li class="rounded-[var(--radius-md)] bg-surface-2 p-3">
                                <div class="mb-1 text-xs text-muted"><strong class="text-fg">{{ $c->user?->name ?? '—' }}</strong> · {{ $c->created_at->translatedFormat('j M H:i') }}</div>
                                <p class="whitespace-pre-line">{{ $c->body }}</p>
                            </li>
                        @endif
                    @endforeach
                </ol>
                @if($can['comment'])
                    <form wire:submit="addComment" class="flex gap-2 border-t border-line-soft p-3">
                        <textarea wire:model="comment" rows="1" class="input min-h-[44px] flex-1 py-2.5" placeholder="{{ __('maintenance.comments.placeholder') }}" aria-label="{{ __('maintenance.comments.label') }}"></textarea>
                        <button type="submit" class="btn btn-primary btn-icon" aria-label="{{ __('maintenance.comments.send') }}"><x-icon name="arrow-right" /></button>
                    </form>
                    @error('comment')<p class="px-3 pb-3 field-error">{{ $message }}</p>@enderror
                @endif
            </section>
        </div>

        <aside class="flex flex-col gap-5">
            {{-- Assegnatari --}}
            <section class="card p-4" aria-labelledby="w-who">
                <div class="mb-2 flex items-center gap-2">
                    <h2 id="w-who" class="flex-1 text-base font-bold">{{ __('maintenance.field.assignees') }}</h2>
                    @if($can['assign'] && ! $editingAssignees)<button type="button" class="btn btn-ghost btn-sm" wire:click="editAssignees"><x-icon name="pencil" class="!w-4 !h-4" />{{ __('maintenance.order.assign') }}</button>@endif
                </div>
                @if($editingAssignees)
                    <form wire:submit="saveAssignees" class="flex flex-col gap-2">
                        @foreach($executors as $u)
                            <label class="flex min-h-10 items-center gap-2"><input type="checkbox" value="{{ $u->id }}" wire:model="assignees" class="h-5 w-5 accent-[var(--mt-accent)]">{{ $u->name }}</label>
                        @endforeach
                        @error('assignees')<span class="field-error">{{ $message }}</span>@enderror
                        <div class="flex gap-2"><button type="submit" class="btn btn-primary btn-sm">{{ __('app.save') }}</button><button type="button" class="btn btn-ghost btn-sm" wire:click="$set('editingAssignees', false)">{{ __('app.cancel') }}</button></div>
                    </form>
                @else
                    <ul class="flex flex-col gap-2">
                        @forelse($o->assignees as $u)
                            <li class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-full bg-surface-2 text-xs font-bold" aria-hidden="true">{{ $u->initials() }}</span><span class="flex-1">{{ $u->name }}</span>@if($running->firstWhere('user_id', $u->id))<x-status tone="ok" icon="clock">{{ __('maintenance.time.working') }}</x-status>@endif</li>
                        @empty
                            <li class="text-sm text-warn">{{ __('maintenance.order.unassigned') }}</li>
                        @endforelse
                    </ul>
                @endif
            </section>

            {{-- Fermo macchina --}}
            @if($o->asset)
                <section class="card p-4" aria-labelledby="w-down">
                    <h2 id="w-down" class="mb-2 text-base font-bold">{{ __('maintenance.downtime.title') }}</h2>
                    @forelse($downtimes as $d)
                        <div class="mb-3 rounded-[var(--radius-md)] border border-line p-3 text-sm" wire:key="dt-{{ $d->id }}">
                            <div class="flex items-center gap-2">
                                @if($d->ended_at)<x-icon name="check-circle" class="!w-4 !h-4 text-ok" />@else<x-icon name="pause-circle" class="!w-4 !h-4 text-ko" />@endif
                                <span class="font-semibold">{{ $d->ended_at ? $fmtMin($d->minutes()) : __('maintenance.downtime.open', ['time' => $fmtMin($d->minutes())]) }}</span>
                            </div>
                            <div class="text-muted">{{ $d->started_at->translatedFormat('j M H:i') }} → {{ $d->ended_at?->translatedFormat('j M H:i') ?? '…' }}</div>
                            @if($can['execute'])
                                <details class="mt-2" @if(isset($downtime[$d->id])) open @endif>
                                    <summary class="cursor-pointer text-accent-fg" wire:click="$set('downtime.{{ $d->id }}', {{ json_encode(['start' => $d->started_at->format('Y-m-d\TH:i'), 'end' => $d->ended_at?->format('Y-m-d\TH:i') ?? '']) }})">{{ __('maintenance.downtime.correct') }}</summary>
                                    <form wire:submit="saveDowntime({{ $d->id }})" class="mt-2 flex flex-col gap-2">
                                        <x-form.input name="downtime.{{ $d->id }}.start" type="datetime-local" :label="__('maintenance.downtime.start')" wire:model="downtime.{{ $d->id }}.start" />
                                        <x-form.input name="downtime.{{ $d->id }}.end" type="datetime-local" :label="__('maintenance.downtime.end')" wire:model="downtime.{{ $d->id }}.end" />
                                        <button type="submit" class="btn btn-secondary btn-sm self-start">{{ __('app.save') }}</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-muted">{{ __('maintenance.downtime.none') }}</p>
                        @if($can['execute'] && $o->status->isActive())<button type="button" class="btn btn-ghost btn-sm mt-2" wire:click="startDowntime" wire:confirm="{{ __('maintenance.downtime.start_confirm') }}"><x-icon name="pause-circle" class="!w-4 !h-4" />{{ __('maintenance.downtime.record') }}</button>@endif
                    @endforelse
                </section>
            @endif

            {{-- Chiusura: note e codici guasto --}}
            @if($o->completed_at)
                <section class="card p-4" aria-labelledby="w-close">
                    <h2 id="w-close" class="mb-2 text-base font-bold">{{ __('maintenance.order.completion') }}</h2>
                    <p class="whitespace-pre-line text-sm">{{ $o->completion_notes }}</p>
                    <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                        @foreach(['failureProblem' => 'problema', 'failureCause' => 'causa', 'failureRemedy' => 'rimedio'] as $rel => $kind)
                            @if($o->{$rel})<dt class="text-muted">{{ __('enums.failure_kind.'.$kind) }}</dt><dd>{{ $o->{$rel}->display() }}</dd>@endif
                        @endforeach
                    </dl>
                </section>
            @endif

            {{-- Costi --}}
            @if($can['costs'])
                <section class="card p-4" aria-labelledby="w-costs">
                    <h2 id="w-costs" class="mb-2 text-base font-bold">{{ __('maintenance.costs.title') }}</h2>
                    <dl class="grid grid-cols-[1fr_auto] gap-y-1.5 text-sm">
                        <dt class="text-muted">{{ __('maintenance.costs.labor', ['time' => $fmtMin($laborMinutes), 'rate' => number_format($o->hourlyRate(), 2, ',', '.')]) }}</dt><dd class="num text-right">€ {{ number_format($costs['labor'], 2, ',', '.') }}</dd>
                        <dt class="text-muted">{{ __('maintenance.costs.external') }}</dt><dd class="num text-right">€ {{ number_format($costs['external'], 2, ',', '.') }}</dd>
                        <dt class="text-muted">{{ __('maintenance.costs.parts') }}</dt><dd class="num text-right">€ {{ number_format($costs['parts'], 2, ',', '.') }}</dd>
                        <dt class="border-t border-line pt-1.5 font-semibold">{{ __('maintenance.costs.total') }}</dt><dd class="num border-t border-line pt-1.5 text-right font-bold">€ {{ number_format($costs['total'], 2, ',', '.') }}</dd>
                    </dl>
                    @if($can['update'])
                        <form wire:submit="saveExternalCost" class="mt-3 flex gap-2">
                            <input type="text" inputmode="decimal" wire:model="externalCost" class="input" placeholder="{{ __('maintenance.costs.external_placeholder') }}" aria-label="{{ __('maintenance.costs.external') }}">
                            <button type="submit" class="btn btn-secondary">{{ __('app.save') }}</button>
                        </form>
                        @error('externalCost')<span class="field-error">{{ $message }}</span>@enderror
                    @endif
                </section>
            @endif
        </aside>
    </div>
</div>
