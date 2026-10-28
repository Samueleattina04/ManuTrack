<?php

namespace App\Livewire\Settings;

use App\Models\Location;
use App\Models\WorkCalendar;
use App\Models\WorkCalendarAssignment;
use App\Models\WorkCalendarShift;
use App\Support\WorkTime\CalendarEditor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Orario di lavoro: elenco dei calendari, creazione da un modello e assegnazione
 * (azienda o ubicazione) da una data.
 */
class WorkCalendars extends Component
{
    public string $name = '';

    public string $preset = 'un_turno';

    /** @var array{target: string, calendar: string, valid_from: string} target: "azienda" o l'id dell'ubicazione */
    public array $assign = ['target' => 'azienda', 'calendar' => '', 'valid_from' => ''];

    public function mount(): void
    {
        $this->authorize('settings.manage');
        $this->assign['valid_from'] = today()->toDateString();
    }

    public function create(CalendarEditor $editor): RedirectResponse|Redirector|null
    {
        $this->authorize('settings.manage');
        $this->validate(['name' => ['required', 'string', 'max:80'], 'preset' => ['required', 'in:'.implode(',', array_keys(CalendarEditor::PRESETS))]], attributes: ['name' => __('worktime.name')]);
        try {
            $calendar = $editor->create($this->name, $this->preset);
        } catch (ValidationException $e) {
            $this->addError('name', $e->validator->errors()->first());

            return null;
        }
        session()->flash('status', __('worktime.created'));

        return redirect()->route('settings.worktime.edit', $calendar);
    }

    public function saveAssignment(CalendarEditor $editor): void
    {
        $this->authorize('settings.manage');
        $location = $this->assign['target'] !== 'azienda' ? Location::query()->find((int) $this->assign['target']) : null;
        $calendar = ctype_digit($this->assign['calendar']) ? WorkCalendar::query()->find((int) $this->assign['calendar']) : null;
        try {
            $editor->assign($location ? 'ubicazione' : 'azienda', $location, $calendar, $this->assign['valid_from']);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('assign.'.$key, $messages[0]);
            }

            return;
        }
        $this->dispatch('toast', message: __('worktime.assign.saved'));
    }

    public function render(): View
    {
        $calendars = WorkCalendar::query()->withCount('assignments')->with(['schedules.shifts'])->orderBy('name')->get();
        $today = today()->toDateString();
        $rows = WorkCalendarAssignment::query()->with(['calendar', 'location'])->orderBy('valid_from')->orderBy('id')->get();

        // Situazione di oggi per azienda e ubicazioni (con l'eredità)
        $current = [];
        foreach ($rows as $r) {
            if ($r->scope === 'macchina' || $r->valid_from->toDateString() > $today) {
                continue;
            }
            $current[$r->scope === 'azienda' ? 'azienda' : $r->location_id] = $r->calendar;
        }
        $labels = Location::labels();
        $locations = Location::query()->get(['id', 'path'])->keyBy('id');
        $effective = [];
        foreach ($labels as $id => $label) {
            $own = array_key_exists($id, $current) ? $current[$id] : null;
            $from = null;
            if (! $own) {
                $chain = array_reverse(array_map('intval', array_filter(explode('/', (string) ($locations[$id]->path ?? '')), fn ($p) => $p !== '')));
                foreach (array_slice($chain, 1) as $parent) {
                    if (! empty($current[$parent])) {
                        $from = $labels[$parent] ?? null;
                        $own = $current[$parent];
                        break;
                    }
                }
                if (! $own && ! empty($current['azienda'])) {
                    $own = $current['azienda'];
                    $from = __('worktime.assign.company');
                }
            }
            $effective[$id] = ['label' => $label, 'calendar' => $own?->name, 'from' => $from, 'own' => array_key_exists($id, $current) && $current[$id] !== null];
        }

        return view('livewire.settings.work-calendars', [
            'calendars' => $calendars->map(fn (WorkCalendar $c) => [
                'model' => $c,
                // Ore a settimana dell'orario in vigore oggi
                'week' => (function () use ($c, $today) {
                    $version = $c->schedules->filter(fn ($s) => $s->valid_from->toDateString() <= $today)->last();
                    $minutes = 0;
                    foreach ($version === null ? [] : $version->shifts as $shift) {
                        /** @var WorkCalendarShift $shift */
                        $m = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
                        $minutes += $shift->end <= $shift->start ? 1440 - $m($shift->start) + $m($shift->end) : $m($shift->end) - $m($shift->start);
                    }

                    return round($minutes / 60, 1);
                })(),
            ]),
            'company' => $current['azienda'] ?? null,
            'effective' => $effective,
            'history' => $rows->reverse()->take(30),
            'presets' => array_keys(CalendarEditor::PRESETS),
        ])->title(__('worktime.title'))->layoutData(['heading' => __('worktime.title')]);
    }
}
