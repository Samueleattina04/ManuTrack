<div class="mx-auto flex max-w-4xl flex-col gap-5">
    <a href="{{ route('requests.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('maintenance.request.title') }}</a>

    <section class="card flex flex-col gap-4 p-4 sm:p-5">
        <div class="flex flex-wrap items-center gap-2">
            <x-status :status="$r->status" />
            @if($r->isUrgent())<x-status tone="ko" icon="bell">{{ __('maintenance.request.urgent') }}</x-status>@endif
            @if($r->machine_stopped)<x-status tone="ko" icon="pause-circle">{{ __('maintenance.request.stopped') }}</x-status>@endif
            <x-priority :priority="$r->priority" />
        </div>
        <p class="whitespace-pre-line text-lg">{{ $r->description }}</p>
        <dl class="grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-[auto_1fr]">
            <dt class="text-muted">{{ __('maintenance.field.asset') }}</dt>
            <dd>
                @if($r->asset)
                    @can('assets.view')<a href="{{ route('assets.show', $r->asset) }}" class="font-medium underline">{{ $r->asset->code }} · {{ $r->asset->name }}</a>@else{{ $r->asset->code }} · {{ $r->asset->name }}@endcan
                    <x-criticality :value="$r->asset->criticality" class="ml-1" />
                @else
                    {{ __('maintenance.request.no_asset') }}
                @endif
            </dd>
            <dt class="text-muted">{{ __('assets.field.location_id') }}</dt><dd>{{ ($r->asset?->location ?? $r->location)?->fullName() ?? '—' }}</dd>
            <dt class="text-muted">{{ __('maintenance.request.by') }}</dt><dd>{{ $r->user?->name ?? '—' }}</dd>
            <dt class="text-muted">{{ __('maintenance.request.when') }}</dt>
            <dd>{{ $r->reported_at?->translatedFormat('j M Y, H:i') }}@if($r->reported_at && $r->created_at->diffInMinutes($r->reported_at, true) > 5) <span class="text-muted">· {{ __('maintenance.request.received', ['time' => $r->created_at->translatedFormat('j M H:i')]) }}</span>@endif</dd>
        </dl>

        @if($r->attachments->isNotEmpty())
            <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                @foreach($r->attachments as $a)
                    <li>
                        @if($a->isPhoto())
                            <a href="{{ $a->url() }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-[var(--radius-md)] bg-surface-2"><img src="{{ $a->url('thumb') }}" alt="{{ __('maintenance.request.photo') }}" loading="lazy" class="aspect-square w-full object-cover"></a>
                        @else
                            <a href="{{ $a->downloadUrl() }}" class="flex aspect-square flex-col items-center justify-center gap-1 rounded-[var(--radius-md)] bg-surface-2 p-2 text-center text-xs"><x-icon name="file-text" />{{ $a->original_name }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Esito della valutazione --}}
    @if($r->status !== \App\Enums\RequestStatus::New)
        <section class="card flex flex-col gap-2 p-4 sm:p-5" aria-labelledby="r-outcome">
            <h2 id="r-outcome" class="text-base font-bold">{{ __('maintenance.request.outcome') }}</h2>
            <p class="text-sm text-fg-2">{{ __('maintenance.request.triaged_by', ['name' => $r->triager?->name ?? '—', 'time' => $r->triaged_at?->translatedFormat('j M Y, H:i')]) }}</p>
            @if($r->triage_note)<p class="whitespace-pre-line">{{ $r->triage_note }}</p>@endif
            @if($r->workOrder)
                <div class="flex flex-wrap items-center gap-2 rounded-[var(--radius-md)] border border-line p-3">
                    <x-icon name="clipboard-list" class="text-accent-fg" />
                    <span class="font-mono">{{ $r->workOrder->number }}</span>
                    <x-status :status="$r->workOrder->status" />
                    @if($r->workOrder->assignees->isNotEmpty())<span class="text-sm text-muted">{{ $r->workOrder->assignees->pluck('name')->implode(', ') }}</span>@endif
                    @if($canSeeOrder)<a href="{{ route('work-orders.show', $r->workOrder) }}" class="btn btn-secondary btn-sm ml-auto">{{ __('maintenance.request.open_order') }}</a>@endif
                </div>
            @endif
            @if($r->duplicateOf)<p class="text-sm">{{ __('maintenance.request.duplicate_of', ['number' => $r->duplicateOf->number]) }} <a href="{{ route('requests.show', $r->duplicateOf) }}" class="underline">{{ __('maintenance.request.open') }}</a></p>@endif
        </section>
    @endif

    {{-- Valutazione --}}
    @if($canTriage)
        <section class="card flex flex-col gap-4 p-4 sm:p-5" aria-labelledby="r-triage">
            <h2 id="r-triage" class="text-lg font-bold">{{ __('maintenance.request.triage') }}</h2>
            <div class="grid gap-2 sm:grid-cols-3">
                <button type="button" wire:click="start('accept')" @class(['btn btn-xl', 'btn-primary' => $mode === 'accept' || $mode === '', 'btn-secondary' => $mode !== 'accept' && $mode !== ''])><x-icon name="check" />{{ __('maintenance.request.accept') }}</button>
                <button type="button" wire:click="start('reject')" @class(['btn btn-xl', 'btn-secondary' => $mode !== 'reject', 'btn-danger' => $mode === 'reject'])><x-icon name="x" />{{ __('maintenance.request.reject') }}</button>
                <button type="button" wire:click="start('duplicate')" @class(['btn btn-xl btn-secondary', '!border-[var(--mt-accent)]' => $mode === 'duplicate'])><x-icon name="layers" />{{ __('maintenance.request.duplicate') }}</button>
            </div>

            @if($mode === 'accept')
                <form wire:submit="accept" class="flex flex-col gap-4 border-t border-line-soft pt-4">
                    <x-form.input name="title" :label="__('maintenance.field.title')" wire:model="title" maxlength="160" />
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="field">
                            <label for="a-prio" class="field-label">{{ __('maintenance.field.priority') }}</label>
                            <select id="a-prio" wire:model="priority" class="input">@foreach($priorities as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select>
                        </div>
                        <div class="field">
                            <label for="a-type" class="field-label">{{ __('maintenance.field.type') }}</label>
                            <select id="a-type" wire:model="type" class="input">@foreach($types as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select>
                        </div>
                        <x-form.input name="dueAt" type="date" :label="__('maintenance.field.due_at')" wire:model="dueAt" />
                    </div>
                    <fieldset>
                        <legend class="field-label mb-2">{{ __('maintenance.field.assignees') }}</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach($executors as $u)
                                <label class="choice !items-center !p-2.5"><input type="checkbox" value="{{ $u->id }}" wire:model="assignees" class="h-5 w-5 accent-[var(--mt-accent)]"><span><span class="block font-medium">{{ $u->name }}</span>@if($u->job_title)<span class="block text-xs text-muted">{{ $u->job_title }}</span>@endif</span></label>
                            @endforeach
                        </div>
                        @error('assignees')<span class="field-error">{{ $message }}</span>@enderror
                    </fieldset>
                    @if($templates->isNotEmpty())
                        <div class="field">
                            <label for="a-tpl" class="field-label">{{ __('maintenance.field.checklist') }}</label>
                            <select id="a-tpl" wire:model="template" class="input"><option value="">{{ __('maintenance.order.no_checklist') }}</option>@foreach($templates as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                        </div>
                    @endif
                    <x-form.input name="note" :label="__('maintenance.request.note_optional')" wire:model="note" />
                    <div><button type="submit" class="btn btn-primary btn-xl" wire:loading.attr="disabled"><x-icon name="clipboard-list" />{{ __('maintenance.request.create_order') }}</button></div>
                </form>
            @elseif($mode === 'reject')
                <form wire:submit="reject" class="flex flex-col gap-4 border-t border-line-soft pt-4">
                    <x-form.input name="note" :label="__('maintenance.field.reject_note')" wire:model="note" :hint="__('maintenance.request.reject_hint')" required />
                    <div><button type="submit" class="btn btn-danger"><x-icon name="x" />{{ __('maintenance.request.reject') }}</button></div>
                </form>
            @elseif($mode === 'duplicate')
                <form wire:submit="markDuplicate" class="flex flex-col gap-4 border-t border-line-soft pt-4">
                    <div class="field">
                        <label for="d-orig" class="field-label">{{ __('maintenance.request.original') }}</label>
                        <select id="d-orig" wire:model="original" class="input">
                            <option value="">—</option>
                            @foreach($candidates as $c)<option value="{{ $c->id }}">{{ $c->number }} · {{ $c->summary(60) }}</option>@endforeach
                        </select>
                        @error('original')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <x-form.input name="note" :label="__('maintenance.request.note_optional')" wire:model="note" />
                    <div><button type="submit" class="btn btn-secondary"><x-icon name="layers" />{{ __('maintenance.request.duplicate') }}</button></div>
                </form>
            @endif
            @error('status')<div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ $message }}</span></div>@enderror
        </section>
    @endif
</div>
