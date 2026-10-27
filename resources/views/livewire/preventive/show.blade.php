@php
    $q = fn ($v) => \App\Models\Part::formatQty($v);
    $outcomeTone = ['on_time' => 'ok', 'late' => 'warn', 'skipped' => 'stop', 'overdue' => 'ko', 'pending' => 'accent'];
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <a href="{{ route('preventive.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('preventive.title') }}</a>

    {{-- Intestazione --}}
    <section class="card flex flex-col gap-4 p-4 sm:p-5">
        <div class="flex flex-wrap items-start gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2 text-sm text-fg-2">
                    <x-plan-state :state="$state" />
                    <span class="inline-flex items-center gap-1.5"><x-icon :name="$p->trigger->icon()" class="!w-4 !h-4" />{{ $p->trigger->label() }} · {{ $p->frequencyLabel() }} · {{ $p->schedule_mode->label() }}</span>
                </div>
                <h1 class="mt-2 text-2xl font-bold">{{ $p->title }}</h1>
                <a href="{{ route('assets.show', $p->asset) }}" class="text-sm underline"><span class="font-mono">{{ $p->asset->code }}</span> · {{ $p->asset->name }}</a>
            </div>
            <dl class="grid grid-cols-[auto_auto] gap-x-4 gap-y-1 text-sm">
                <dt class="text-muted">{{ __('preventive.field.next_due') }}</dt>
                <dd class="num text-right text-lg font-bold">
                    @if($p->isTime()){{ $p->next_due_on?->translatedFormat('j M Y') ?? '—' }}@else{{ $q($p->next_meter_due) }} {{ $p->meter->unit ?? '' }}@endif
                </dd>
                @unless($p->isTime())
                    <dt class="text-muted">{{ $p->meter->name ?? __('preventive.field.meter_id') }}</dt><dd class="num text-right">{{ $q($p->meter->current_value ?? 0) }} {{ $p->meter->unit ?? '' }}</dd>
                @endunless
                <dt class="text-muted">{{ __('preventive.field.last_done') }}</dt><dd class="num text-right">{{ $p->last_done_at?->translatedFormat('j M Y') ?? '—' }}</dd>
                <dt class="text-muted">{{ __('preventive.field.assignee_id') }}</dt><dd class="text-right">{{ $p->assignee->name ?? '—' }}</dd>
            </dl>
        </div>
        @if($canManage)
            <div class="flex flex-wrap gap-2">
                @if($canGenerate)
                    <button type="button" class="btn btn-primary" wire:click="generate" wire:confirm="{{ __('preventive.plan.generate_confirm') }}"><x-icon name="plus" />{{ __('preventive.plan.generate') }}</button>
                @endif
                <a href="{{ route('preventive.edit', $p) }}" class="btn btn-secondary"><x-icon name="pencil" />{{ __('app.edit') }}</a>
            </div>
        @endif
    </section>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="flex flex-col gap-5">
            {{-- Ordine aperto o prossimo --}}
            <section class="card p-4" aria-labelledby="pv-current">
                <h2 id="pv-current" class="mb-2 text-base font-bold">{{ __('preventive.plan.open_order') }}</h2>
                @if($p->hasOpenOrder())
                    @php $o = $p->currentOrder; @endphp
                    <a href="{{ route('work-orders.show', $o) }}" class="flex flex-wrap items-center gap-3 rounded-[var(--radius-md)] border border-line px-3 py-2.5 hover:bg-surface-2">
                        <span class="font-mono text-sm">{{ $o->number }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ $o->title }}</span>
                        @if($o->due_at)<span @class(['text-sm', 'font-semibold text-ko' => $o->isOverdue()])>{{ __('maintenance.order.due', ['date' => $o->due_at->translatedFormat('j M')]) }}</span>@endif
                        <x-status :status="$o->status" />
                    </a>
                @else
                    <p class="text-sm text-fg-2">
                        @if(! $p->is_active)
                            {{ __('preventive.errors.inactive') }}
                        @elseif($p->isTime() && $p->next_due_on)
                            {{ __('preventive.plan.no_order', ['when' => __('preventive.plan.when_date', ['date' => $p->next_due_on->copy()->subDays($p->lead_days)->translatedFormat('j F Y')])]) }}
                        @elseif(! $p->isTime())
                            {{ __('preventive.plan.no_order', ['when' => __('preventive.plan.when_meter', ['value' => $q((float) $p->next_meter_due - (float) $p->meter_lead), 'unit' => $p->meter->unit ?? ''])]) }}
                        @endif
                    </p>
                @endif
            </section>

            @if($p->description)
                <section class="card p-4" aria-labelledby="pv-desc">
                    <h2 id="pv-desc" class="mb-2 text-base font-bold">{{ __('preventive.field.description') }}</h2>
                    <p class="whitespace-pre-line text-sm">{{ $p->description }}</p>
                </section>
            @endif

            {{-- Interventi --}}
            <section class="card overflow-hidden" aria-labelledby="pv-history">
                <h2 id="pv-history" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('preventive.plan.history') }}</h2>
                <ul>
                    @forelse($history as $o)
                        @php $out = \App\Support\Preventive\PlanCompliance::outcome($o); @endphp
                        <li class="border-b border-line-soft last:border-0" wire:key="h-{{ $o->id }}">
                            <a href="{{ route('work-orders.show', $o) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-sm hover:bg-surface-2">
                                <span class="font-mono">{{ $o->number }}</span>
                                <span class="min-w-0 flex-1 text-fg-2">
                                    {{ __('preventive.field.planned_due') }} {{ $o->plan_due_on?->translatedFormat('j M Y') ?? '—' }}
                                    @if($o->completed_at) · {{ __('preventive.plan.done_on', ['date' => $o->completed_at->translatedFormat('j M Y')]) }}@endif
                                </span>
                                @if($out !== 'pending')<x-status :tone="$outcomeTone[$out]">{{ __('preventive.compliance.'.($out === 'overdue' ? 'overdue' : $out)) }}</x-status>@else<x-status :status="$o->status" />@endif
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-muted">{{ __('preventive.plan.no_history') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside class="flex flex-col gap-5">
            {{-- Prossime scadenze --}}
            <section class="card p-4" aria-labelledby="pv-next">
                <h2 id="pv-next" class="mb-2 text-base font-bold">{{ __('preventive.plan.upcoming') }}</h2>
                @if($upcoming === [])
                    <p class="text-sm text-muted">{{ $p->isTime() ? '—' : __('preventive.plan.no_estimate') }}</p>
                @else
                    <ul class="flex flex-col gap-1 text-sm">
                        @foreach($upcoming as $u)
                            <li class="flex items-center gap-2"><x-icon name="calendar" class="!w-4 !h-4 text-muted" /><span class="num">{{ $u['date']->translatedFormat('D j M Y') }}</span>@if($u['estimated'])<span class="text-xs text-muted">· {{ __('preventive.plan.estimated') }}</span>@endif</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Rispetto del piano --}}
            <section class="card p-4" aria-labelledby="pv-comp">
                <h2 id="pv-comp" class="text-base font-bold">{{ __('preventive.compliance.title') }}</h2>
                <p class="mb-2 text-xs text-muted">{{ __('preventive.compliance.period', ['months' => 12]) }}</p>
                @if($compliance['total'] === 0)
                    <p class="text-sm text-fg-2">{{ __('preventive.compliance.none') }}</p>
                @else
                    <p class="num text-2xl font-bold">{{ number_format($compliance['rate'], 0, ',', '.') }}%</p>
                    <p class="text-sm text-fg-2">{{ __('preventive.compliance.on_time') }} {{ $compliance['on_time'] }} · {{ __('preventive.compliance.late') }} {{ $compliance['late'] }} · {{ __('preventive.compliance.skipped') }} {{ $compliance['skipped'] }} · {{ __('preventive.compliance.overdue') }} {{ $compliance['overdue'] }}</p>
                @endif
            </section>

            {{-- Ricambi previsti --}}
            <section class="card p-4" aria-labelledby="pv-parts">
                <h2 id="pv-parts" class="mb-2 text-base font-bold">{{ __('preventive.plan.parts') }}</h2>
                <ul class="flex flex-col gap-1 text-sm">
                    @forelse($p->parts as $part)
                        <li class="flex items-center gap-2" wire:key="pp-{{ $part->id }}">
                            <span class="min-w-0 flex-1 truncate">@can('inventory.view')<a href="{{ route('inventory.parts.show', $part) }}" class="hover:underline">@endcan<span class="font-mono text-xs text-muted">{{ $part->code }}</span> {{ $part->name }}@can('inventory.view')</a>@endcan</span>
                            <span class="num">× {{ $q($part->pivot->quantity) }} {{ $part->unit }}</span>
                            @if($canManage)<button type="button" class="btn btn-ghost btn-icon btn-sm text-muted" wire:click="removePart({{ $part->id }})" aria-label="{{ __('app.delete') }} {{ $part->code }}"><x-icon name="x" class="!w-4 !h-4" /></button>@endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('preventive.plan.no_parts') }}</li>
                    @endforelse
                </ul>
                @if($canManage && $partOptions->isNotEmpty())
                    <form wire:submit="addPart" class="mt-3 flex gap-2">
                        <select wire:model="partToAdd" class="input min-w-0 flex-1" aria-label="{{ __('preventive.plan.add_part') }}"><option value="">{{ __('preventive.plan.choose_part') }}</option>@foreach($partOptions as $o)<option value="{{ $o->id }}">{{ $o->code }} · {{ $o->name }}</option>@endforeach</select>
                        <input type="text" inputmode="decimal" wire:model="partQty" class="input w-16 num" aria-label="{{ __('inventory.field.quantity') }}">
                        <button type="submit" class="btn btn-secondary btn-icon" aria-label="{{ __('preventive.plan.add_part') }}"><x-icon name="plus" /></button>
                    </form>
                    @error('partToAdd')<span class="field-error">{{ $message }}</span>@enderror
                @endif
            </section>

            @if($canManage)
                <section class="card flex flex-col gap-3 p-4" aria-labelledby="pv-more">
                    <h2 id="pv-more" class="text-base font-bold">{{ __('preventive.plan.duplicate') }}</h2>
                    <form wire:submit="duplicate" class="flex gap-2">
                        <select wire:model="copyTo" class="input min-w-0 flex-1" aria-label="{{ __('preventive.plan.duplicate_choose') }}"><option value="">{{ __('preventive.plan.duplicate_choose') }}</option>@foreach($assetOptions as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>@endforeach</select>
                        <button type="submit" class="btn btn-secondary"><x-icon name="clipboard-list" class="!w-4 !h-4" />{{ __('preventive.plan.copy') }}</button>
                    </form>
                    @error('copyTo')<span class="field-error">{{ $message }}</span>@enderror
                    <button type="button" class="btn btn-ghost btn-sm self-start text-ko" wire:click="delete" wire:confirm="{{ __('preventive.plan.delete_confirm') }}"><x-icon name="trash" class="!w-4 !h-4" />{{ __('preventive.plan.delete') }}</button>
                </section>
            @endif
        </aside>
    </div>
</div>
