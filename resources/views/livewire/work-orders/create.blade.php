<form wire:submit="save" class="mx-auto flex max-w-3xl flex-col gap-5" novalidate>
    <a href="{{ route('work-orders.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('maintenance.order.title') }}</a>

    <section class="card flex flex-col gap-4 p-5">
        <fieldset>
            <legend class="field-label mb-2">{{ __('maintenance.field.type') }}</legend>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach($types as $t)
                    <label class="choice !items-center"><input type="radio" value="{{ $t->value }}" wire:model="form.type" class="sr-only"><x-icon :name="$t->icon()" class="text-accent-fg" /><span class="font-semibold">{{ $t->label() }}</span></label>
                @endforeach
            </div>
        </fieldset>
        <x-form.input name="form.title" :label="__('maintenance.field.title')" wire:model="form.title" maxlength="160" required />
        <div class="field">
            <label for="c-desc" class="field-label">{{ __('maintenance.field.description') }}</label>
            <textarea id="c-desc" wire:model="form.description" rows="4" class="input py-2.5"></textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="field">
                <label for="c-asset" class="field-label">{{ __('maintenance.field.asset') }}</label>
                <select id="c-asset" wire:model="form.asset_id" class="input"><option value="">{{ __('maintenance.order.no_asset') }}</option>@foreach($assets as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>@endforeach</select>
            </div>
            <div class="field">
                <label for="c-prio" class="field-label">{{ __('maintenance.field.priority') }}</label>
                <select id="c-prio" wire:model="form.priority" class="input">@foreach($priorities as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select>
            </div>
            <x-form.input name="form.due_at" type="date" :label="__('maintenance.field.due_at')" wire:model="form.due_at" />
            @if($templates->isNotEmpty())
                <div class="field">
                    <label for="c-tpl" class="field-label">{{ __('maintenance.field.checklist') }}</label>
                    <select id="c-tpl" wire:model="form.checklist_template_id" class="input"><option value="">{{ __('maintenance.order.no_checklist') }}</option>@foreach($templates as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                </div>
            @endif
        </div>
        @if($canAssign)
            <fieldset>
                <legend class="field-label mb-2">{{ __('maintenance.field.assignees') }}</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach($executors as $u)
                        <label class="choice !items-center !p-2.5"><input type="checkbox" value="{{ $u->id }}" wire:model="form.assignees" class="h-5 w-5 accent-[var(--mt-accent)]"><span class="font-medium">{{ $u->name }}</span></label>
                    @endforeach
                </div>
                @error('form.assignees')<span class="field-error">{{ $message }}</span>@enderror
            </fieldset>
        @else
            <p class="text-sm text-muted">{{ __('maintenance.order.self_assigned') }}</p>
        @endif
    </section>

    <div class="flex justify-end gap-2">
        <a href="{{ route('work-orders.index') }}" class="btn btn-secondary">{{ __('app.cancel') }}</a>
        <button type="submit" class="btn btn-primary btn-xl" wire:loading.attr="disabled"><x-icon name="check" />{{ __('maintenance.order.create') }}</button>
    </div>
</form>
