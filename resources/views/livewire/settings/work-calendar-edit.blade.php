<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('settings.worktime') }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" class="!h-4 !w-4" />{{ __('worktime.edit.back') }}</a>
    </div>

    {{-- Nome --}}
    <form wire:submit="rename" class="card flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-[14rem] flex-1"><x-form.input name="name" :label="__('worktime.name')" wire:model="name" maxlength="80" required /></div>
        <button type="submit" class="btn btn-secondary">{{ __('worktime.edit.rename') }}</button>
    </form>

    {{-- Orario settimanale --}}
    <section class="card overflow-hidden" aria-labelledby="c-week">
        <div class="flex flex-wrap items-center gap-2 border-b border-line-soft px-4 py-3">
            <h2 id="c-week" class="flex-1 text-base font-bold">{{ __('worktime.edit.versions') }}</h2>
            <div class="-mx-1 flex flex-wrap gap-1" role="group" aria-label="{{ __('worktime.edit.versions') }}">
                @foreach($versions as $v)
                    <button type="button" wire:click="loadVersion({{ $v->id }})" @class(['status-filter', 'is-active' => $v->id === $versionId]) aria-pressed="{{ $v->id === $versionId ? 'true' : 'false' }}">
                        {{ $v->valid_from->toDateString() === \App\Support\WorkTime\CalendarEditor::FIRST_DAY ? __('worktime.edit.version_always') : __('worktime.edit.version_from', ['date' => $v->valid_from->translatedFormat('j M Y')]) }}
                    </button>
                @endforeach
            </div>
        </div>
        @if($version)
            @unless($editable)
                <div class="alert alert-info m-4 mb-0" role="note"><x-icon name="lock" /><span>{{ __('worktime.edit.version_locked') }}</span></div>
            @endunless
            <form wire:submit="saveShifts" class="flex flex-col gap-3 p-4">
                <p class="text-sm text-muted">{{ __('worktime.edit.night_hint') }}</p>
                @error('shifts')<div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ $message }}</span></div>@enderror
                @foreach(range(1, 7) as $d)
                    @error('shifts.'.$d)<div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ __('worktime.weekdays.'.$d) }}: {{ $message }}</span></div>@enderror
                    <fieldset class="grid gap-2 border-b border-line-soft pb-3 sm:grid-cols-[8rem_minmax(0,1fr)] sm:items-start">
                        <legend class="sr-only">{{ __('worktime.weekdays.'.$d) }}</legend>
                        <span class="pt-2 font-semibold" aria-hidden="true">{{ __('worktime.weekdays.'.$d) }}</span>
                        <div class="flex flex-col gap-2">
                            @forelse($shifts[$d] ?? [] as $i => $pair)
                                <div class="flex flex-wrap items-center gap-2" wire:key="s-{{ $d }}-{{ $i }}">
                                    <input type="time" step="300" class="input w-32" wire:model="shifts.{{ $d }}.{{ $i }}.0" aria-label="{{ __('worktime.weekdays.'.$d) }}, {{ __('worktime.edit.from') }}" @disabled(! $editable)>
                                    <span aria-hidden="true">–</span>
                                    <input type="time" step="300" class="input w-32" wire:model="shifts.{{ $d }}.{{ $i }}.1" aria-label="{{ __('worktime.weekdays.'.$d) }}, {{ __('worktime.edit.to') }}" @disabled(! $editable)>
                                    @if($editable)<button type="button" class="btn btn-ghost btn-sm" wire:click="removeShift({{ $d }}, {{ $i }})">{{ __('worktime.edit.remove_shift') }}</button>@endif
                                </div>
                            @empty
                                <span class="pt-2 text-sm text-muted">{{ __('worktime.kinds.chiuso') }}</span>
                            @endforelse
                            @if($editable)<button type="button" class="btn btn-ghost btn-sm self-start" wire:click="addShift({{ $d }})"><x-icon name="plus" class="!h-4 !w-4" />{{ __('worktime.edit.add_shift') }}</button>@endif
                        </div>
                    </fieldset>
                @endforeach
                @if($editable)
                    <button type="submit" class="btn btn-primary self-start"><x-icon name="check" />{{ __('worktime.edit.save_hours') }}</button>
                @endif
            </form>
        @endif
        <form wire:submit="createVersion" class="flex flex-wrap items-end gap-3 border-t border-line-soft p-4">
            <div><x-form.input name="newVersionFrom" type="date" :label="__('worktime.edit.new_version')" wire:model="newVersionFrom" min="{{ $today }}" /></div>
            <button type="submit" class="btn btn-secondary"><x-icon name="plus" />{{ __('worktime.edit.new_version_create') }}</button>
        </form>
    </section>

    {{-- Giorni speciali --}}
    <section class="card overflow-hidden" aria-labelledby="c-days">
        <div class="flex flex-wrap items-end gap-3 border-b border-line-soft px-4 py-3">
            <h2 id="c-days" class="flex-1 text-base font-bold">{{ __('worktime.edit.days') }}</h2>
            <div class="field">
                <label for="c-year" class="field-label">{{ __('worktime.edit.year') }}</label>
                <select id="c-year" wire:model.live="year" class="input">@foreach($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select>
            </div>
            <button type="button" class="btn btn-secondary" wire:click="proposeHolidays"><x-icon name="calendar" />{{ __('worktime.edit.propose') }}</button>
        </div>
        @error('days')<div class="alert alert-ko m-4" role="alert"><x-icon name="alert-triangle" /><span>{{ $message }}</span></div>@enderror
        @if($days->isEmpty())
            <p class="px-4 py-6 text-center text-muted">{{ __('worktime.edit.none') }}</p>
        @else
            <ul class="divide-y divide-line-soft">
                @foreach($days as $day)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="day-{{ $day->id }}">
                        <span class="num w-28 shrink-0 font-semibold">{{ $day->date->translatedFormat('D j M') }}</span>
                        <span class="min-w-0 flex-1">{{ $day->label ?? '—' }} <span class="text-xs text-muted">· {{ __('worktime.source.'.$day->source) }}</span></span>
                        <x-status :tone="['chiuso' => 'stop', 'orario' => 'mnt', 'lavorativo' => 'ok'][$day->kind]">{{ __('worktime.kinds.'.$day->kind) }}@if($day->kind === 'orario') {{ collect($day->hours)->map(fn ($h) => $h[0].'–'.$h[1])->implode(', ') }}@endif</x-status>
                        @if($day->isEditable())
                            @if($day->kind === 'lavorativo')
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="restoreDay({{ $day->id }})">{{ __('worktime.edit.restore') }}</button>
                            @else
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="removeDay({{ $day->id }})">{{ __('worktime.edit.remove') }}</button>
                            @endif
                        @else
                            <span class="text-xs text-muted" title="{{ __('worktime.edit.past_locked') }}"><x-icon name="lock" class="!h-4 !w-4" /><span class="sr-only">{{ __('worktime.edit.past_locked') }}</span></span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="grid gap-4 border-t border-line-soft p-4 lg:grid-cols-2">
            <form wire:submit="addClosure" class="flex flex-col gap-3">
                <h3 class="font-semibold">{{ __('worktime.edit.closure') }}</h3>
                <div class="grid grid-cols-2 gap-3">
                    <x-form.input name="closure.from" type="date" :label="__('worktime.edit.from')" wire:model="closure.from" min="{{ $today }}" />
                    <x-form.input name="closure.to" type="date" :label="__('worktime.edit.to')" wire:model="closure.to" min="{{ $today }}" />
                </div>
                <x-form.input name="closure.label" :label="__('worktime.edit.label')" wire:model="closure.label" maxlength="80" />
                @error('closure.date')<span class="field-error">{{ $message }}</span>@enderror
                <button type="submit" class="btn btn-secondary self-start"><x-icon name="plus" />{{ __('worktime.edit.add_closure') }}</button>
            </form>
            <form wire:submit="setSpecial" class="flex flex-col gap-3">
                <h3 class="font-semibold">{{ __('worktime.edit.special') }}</h3>
                <x-form.input name="special.date" type="date" :label="__('worktime.edit.date')" wire:model="special.date" min="{{ $today }}" />
                <div class="grid grid-cols-2 gap-3">
                    <x-form.input name="special.start" type="time" :label="__('worktime.edit.from')" wire:model="special.start" />
                    <x-form.input name="special.end" type="time" :label="__('worktime.edit.to')" wire:model="special.end" />
                </div>
                @error('special.hours')<span class="field-error">{{ $message }}</span>@enderror
                <x-form.input name="special.label" :label="__('worktime.edit.label')" wire:model="special.label" maxlength="80" />
                <button type="submit" class="btn btn-secondary self-start"><x-icon name="check" />{{ __('worktime.edit.add_special') }}</button>
            </form>
        </div>
    </section>

    <section class="card p-4" aria-labelledby="c-danger">
        <h2 id="c-danger" class="sr-only">{{ __('worktime.edit.delete') }}</h2>
        @error('calendar')<div class="alert alert-ko mb-3" role="alert"><x-icon name="alert-triangle" /><span>{{ $message }}</span></div>@enderror
        <button type="button" class="btn btn-ghost text-ko" wire:click="deleteCalendar" wire:confirm="{{ __('worktime.edit.delete_confirm', ['name' => $calendar->name]) }}"><x-icon name="trash" />{{ __('worktime.edit.delete') }}</button>
    </section>
</div>
