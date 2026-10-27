@php $err = fn ($k) => $errors->first('form.'.$k); @endphp
<form wire:submit="save" class="mx-auto flex max-w-3xl flex-col gap-5" novalidate>
    <a href="{{ $plan ? route('preventive.show', $plan) : route('preventive.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ $plan ? $plan->title : __('preventive.title') }}</a>

    @if($errors->any())<div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ __('assets.form.has_errors') }}</span></div>@endif

    {{-- Cosa e dove --}}
    <section class="card flex flex-col gap-4 p-5" aria-labelledby="pp-what">
        <h2 id="pp-what" class="text-lg font-bold">{{ __('preventive.field.title') }}</h2>
        <x-form.input name="form.title" :label="__('preventive.field.title')" wire:model="form.title" :placeholder="__('preventive.plan.title_placeholder')" />
        <div class="field">
            <label for="pp-asset" class="field-label">{{ __('preventive.field.asset_id') }}</label>
            <select id="pp-asset" wire:model.live="form.asset_id" class="input" @error('form.asset_id') aria-invalid="true" @enderror>
                <option value="">—</option>
                @foreach($assets as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>@endforeach
            </select>
            @if($err('asset_id'))<span class="field-error">{{ $err('asset_id') }}</span>@endif
        </div>
        <div class="field">
            <label for="pp-desc" class="field-label">{{ __('preventive.field.description') }}</label>
            <textarea id="pp-desc" wire:model="form.description" rows="4" class="input py-2.5"></textarea>
        </div>
    </section>

    {{-- Quando --}}
    <section class="card flex flex-col gap-4 p-5" aria-labelledby="pp-when">
        <h2 id="pp-when" class="text-lg font-bold">{{ __('preventive.field.trigger') }}</h2>
        <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-labelledby="pp-when">
            @foreach(\App\Enums\PlanTrigger::cases() as $t)
                <label @class(['flex min-h-[56px] cursor-pointer items-center gap-3 rounded-[var(--radius-md)] border px-4 has-[:focus-visible]:outline has-[:focus-visible]:outline-2', 'border-accent bg-surface-2 font-semibold' => $form['trigger'] === $t->value, 'border-line' => $form['trigger'] !== $t->value])>
                    <input type="radio" wire:model.live="form.trigger" value="{{ $t->value }}" class="sr-only">
                    <x-icon :name="$t->icon()" />{{ $t->label() }}
                </label>
            @endforeach
        </div>

        @if($form['trigger'] === 'tempo')
            <div class="grid gap-4 sm:grid-cols-[8rem_1fr_1fr]">
                <x-form.input name="form.interval_value" :label="__('preventive.field.interval_value')" wire:model="form.interval_value" inputmode="numeric" />
                <div class="field">
                    <label for="pp-unit" class="field-label">{{ __('preventive.field.interval_unit') }}</label>
                    <select id="pp-unit" wire:model="form.interval_unit" class="input">@foreach($units as $u)<option value="{{ $u->value }}">{{ $u->label() }}</option>@endforeach</select>
                </div>
                <x-form.input name="form.next_due_on" type="date" :label="__('preventive.field.next_due_on')" wire:model="form.next_due_on" :hint="__('preventive.hint.next_due_on')" />
            </div>
            <x-form.input name="form.lead_days" :label="__('preventive.field.lead_days')" wire:model="form.lead_days" inputmode="numeric" :hint="__('preventive.hint.lead_days')" class="max-w-[10rem]" />
        @else
            @if($form['asset_id'] !== '' && $meters->isEmpty())
                <p class="alert alert-warn"><x-icon name="info" /><span>{{ __('preventive.hint.no_meters') }}</span></p>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="field">
                    <label for="pp-meter" class="field-label">{{ __('preventive.field.meter_id') }}</label>
                    <select id="pp-meter" wire:model.live="form.meter_id" class="input" @error('form.meter_id') aria-invalid="true" @enderror>
                        <option value="">—</option>
                        @foreach($meters as $m)<option value="{{ $m->id }}">{{ $m->name }} · {{ \App\Models\Part::formatQty($m->current_value ?? 0) }} {{ $m->unit }}</option>@endforeach
                    </select>
                    @if($err('meter_id'))<span class="field-error">{{ $err('meter_id') }}</span>@endif
                </div>
                <x-form.input name="form.meter_interval" :label="__('preventive.field.meter_interval')" wire:model.blur="form.meter_interval" inputmode="decimal" />
                <x-form.input name="form.next_meter_due" :label="__('preventive.field.next_meter_due')" wire:model="form.next_meter_due" inputmode="decimal" :hint="__('preventive.hint.next_meter_due')" />
                <x-form.input name="form.meter_lead" :label="__('preventive.field.meter_lead')" wire:model="form.meter_lead" inputmode="decimal" :hint="__('preventive.hint.meter_lead')" />
            </div>
        @endif

        <fieldset class="flex flex-col gap-2">
            <legend class="field-label mb-1">{{ __('preventive.field.schedule_mode') }}</legend>
            @foreach($modes as $m)
                <label class="flex cursor-pointer items-start gap-3 rounded-[var(--radius-md)] border border-line p-3">
                    <input type="radio" wire:model="form.schedule_mode" value="{{ $m->value }}" class="mt-1 h-5 w-5 accent-[var(--mt-accent)]">
                    <span><span class="font-semibold">{{ $m->label() }}</span><span class="block text-sm text-fg-2">{{ __('preventive.hint.'.$m->value.($form['trigger'] === 'contatore' ? '_meter' : '')) }}</span></span>
                </label>
            @endforeach
        </fieldset>
    </section>

    {{-- Chi e come --}}
    <section class="card flex flex-col gap-4 p-5" aria-labelledby="pp-how">
        <h2 id="pp-how" class="text-lg font-bold">{{ __('preventive.plan.how') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="field">
                <label for="pp-who" class="field-label">{{ __('preventive.field.assignee_id') }}</label>
                <select id="pp-who" wire:model="form.assignee_id" class="input"><option value="">—</option>@foreach($people as $u)<option value="{{ $u->id }}">{{ $u->name }}@if($u->job_title) · {{ $u->job_title }}@endif</option>@endforeach</select>
                @if($err('assignee_id'))<span class="field-error">{{ $err('assignee_id') }}</span>@endif
            </div>
            <div class="field">
                <label for="pp-prio" class="field-label">{{ __('preventive.field.priority') }}</label>
                <select id="pp-prio" wire:model="form.priority" class="input">@foreach($priorities as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select>
            </div>
            <div class="field">
                <label for="pp-tpl" class="field-label">{{ __('preventive.field.checklist_template_id') }}</label>
                <select id="pp-tpl" wire:model="form.checklist_template_id" class="input"><option value="">—</option>@foreach($templates as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
            </div>
            <x-form.input name="form.estimated_minutes" :label="__('preventive.field.estimated_minutes')" wire:model="form.estimated_minutes" inputmode="numeric" />
        </div>
        @if($plan)
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.is_active" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('preventive.field.is_active') }}</label>
        @endif
    </section>

    <div class="flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary btn-xl" wire:loading.attr="disabled"><x-icon name="check" />{{ $plan ? __('app.save') : __('preventive.plan.create') }}</button>
        <a href="{{ $plan ? route('preventive.show', $plan) : route('preventive.index') }}" class="btn btn-ghost btn-xl">{{ __('app.cancel') }}</a>
    </div>
</form>
