<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <p class="max-w-prose text-fg-2">{{ __('worktime.intro') }}</p>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- Calendari --}}
        <section class="card overflow-hidden" aria-labelledby="w-cal">
            <h2 id="w-cal" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('worktime.calendars') }}</h2>
            @if($calendars->isEmpty())
                <p class="px-4 py-8 text-center text-muted">{{ __('worktime.empty') }}</p>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach($calendars as $c)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <span class="grid h-10 w-10 place-items-center rounded-[12px] bg-surface-2 text-accent-fg"><x-icon name="clock" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ $c['model']->name }}</p>
                                <p class="text-sm text-muted">{{ __('worktime.week_hours', ['h' => number_format($c['week'], 1, ',', '.')]) }} · {{ trans_choice('worktime.used_by', $c['model']->assignments_count, ['count' => $c['model']->assignments_count]) }}</p>
                            </div>
                            <a href="{{ route('settings.worktime.edit', $c['model']) }}" class="btn btn-secondary btn-sm">{{ __('worktime.open') }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Nuovo calendario --}}
        <section class="card flex flex-col gap-3 p-4" aria-labelledby="w-new">
            <h2 id="w-new" class="text-base font-bold">{{ __('worktime.new') }}</h2>
            <form wire:submit="create" class="flex flex-col gap-3">
                <x-form.input name="name" :label="__('worktime.name')" wire:model="name" maxlength="80" required />
                <fieldset class="flex flex-col gap-1.5">
                    <legend class="field-label mb-1">{{ __('worktime.preset') }}</legend>
                    @foreach($presets as $p)
                        <label class="flex min-h-[40px] items-center gap-2.5 text-sm"><input type="radio" wire:model="preset" value="{{ $p }}" class="h-5 w-5 accent-[var(--mt-accent)]">{{ __('worktime.presets.'.$p) }}</label>
                    @endforeach
                </fieldset>
                <button type="submit" class="btn btn-primary self-start"><x-icon name="plus" />{{ __('worktime.create') }}</button>
            </form>
        </section>
    </div>

    {{-- Dove si applica --}}
    <section class="card overflow-hidden" aria-labelledby="w-assign">
        <div class="border-b border-line-soft px-4 py-3">
            <h2 id="w-assign" class="text-base font-bold">{{ __('worktime.assign.title') }}</h2>
            <p class="mt-1 max-w-prose text-sm text-muted">{{ __('worktime.assign.hint') }}</p>
        </div>
        @if($calendars->isNotEmpty())
            <form wire:submit="saveAssignment" class="grid gap-3 border-b border-line-soft p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_12rem_auto] lg:items-end">
                <div class="field">
                    <label for="w-target" class="field-label">{{ __('worktime.assign.scope') }}</label>
                    <select id="w-target" wire:model="assign.target" class="input">
                        <option value="azienda">{{ __('worktime.assign.company') }}</option>
                        @foreach($effective as $id => $row)<option value="{{ $id }}">{{ $row['label'] }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="w-calendar" class="field-label">{{ __('worktime.assign.calendar') }}</label>
                    <select id="w-calendar" wire:model="assign.calendar" class="input">
                        <option value="">{{ __('worktime.inherit') }} / {{ __('worktime.h24') }}</option>
                        @foreach($calendars as $c)<option value="{{ $c['model']->id }}">{{ $c['model']->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-form.input name="assign.valid_from" type="date" :label="__('worktime.assign.valid_from')" wire:model.live="assign.valid_from" />
                    @if($assign['valid_from'] !== '' && $assign['valid_from'] < today()->toDateString())
                        <p class="mt-1 text-xs text-warn" role="note">{{ __('worktime.assign.past_warning') }}</p>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('worktime.assign.save') }}</button>
            </form>
        @endif
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>{{ __('worktime.assign.scope') }}</th><th>{{ __('worktime.assign.current') }}</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="font-semibold">{{ __('worktime.assign.company') }}</td>
                        <td>{{ $company?->name ?? __('worktime.h24') }}</td>
                    </tr>
                    @foreach($effective as $id => $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>
                                @if($row['calendar'])
                                    <span @class(['font-semibold' => $row['own']])>{{ $row['calendar'] }}</span>
                                    @if($row['from'])<span class="text-sm text-muted">· {{ __('worktime.assign.inherited_from', ['name' => $row['from']]) }}</span>@endif
                                @else
                                    <span class="text-muted">{{ __('worktime.h24') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($history->isNotEmpty())
            <details class="border-t border-line-soft px-4 py-3 text-sm">
                <summary class="cursor-pointer font-semibold">{{ __('worktime.assign.history') }}</summary>
                <ul class="mt-2 flex flex-col gap-1">
                    @foreach($history as $h)
                        <li><span class="num">{{ $h->valid_from->translatedFormat('j M Y') }}</span> · {{ $h->scope === 'ubicazione' ? ($h->location->name ?? '—') : __('worktime.scope.'.$h->scope) }} → <strong>{{ $h->calendar->name ?? __('worktime.inherit') }}</strong></li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>
</div>
