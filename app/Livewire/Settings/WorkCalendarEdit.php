<?php

namespace App\Livewire\Settings;

use App\Models\WorkCalendar;
use App\Models\WorkCalendarException;
use App\Models\WorkCalendarSchedule;
use App\Support\WorkTime\CalendarEditor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Un calendario di lavoro: nome, orari settimanali (versioni "valido dal"), giorni speciali.
 */
class WorkCalendarEdit extends Component
{
    public WorkCalendar $calendar;

    public string $name = '';

    public ?int $versionId = null;

    /** @var array<int, list<array{0: string, 1: string}>> giorno 1-7 => turni */
    public array $shifts = [];

    public string $newVersionFrom = '';

    public int $year;

    /** @var array{from: string, to: string, label: string} */
    public array $closure = ['from' => '', 'to' => '', 'label' => ''];

    /** @var array{date: string, start: string, end: string, label: string} */
    public array $special = ['date' => '', 'start' => '', 'end' => '', 'label' => ''];

    public function mount(WorkCalendar $calendar): void
    {
        $this->authorize('settings.manage');
        $this->calendar = $calendar;
        $this->name = $calendar->name;
        $this->year = (int) today()->year;
        $today = today()->toDateString();
        $versions = $calendar->schedules()->get();
        $this->loadVersion(($versions->first(fn (WorkCalendarSchedule $s) => $s->isEditable() && $s->valid_from->toDateString() > $today)
            ?? $versions->filter(fn (WorkCalendarSchedule $s) => $s->valid_from->toDateString() <= $today)->last()
            ?? $versions->last())?->id);
    }

    public function loadVersion(?int $id): void
    {
        $version = $id ? $this->calendar->schedules()->with('shifts')->find($id) : null;
        $this->versionId = $version?->id;
        $this->shifts = array_fill_keys(range(1, 7), []);
        foreach ($version === null ? [] : $version->shifts as $shift) {
            $this->shifts[$shift->weekday][] = [$shift->start, $shift->end];
        }
        $this->resetErrorBag();
    }

    public function addShift(int $day): void
    {
        if ($day >= 1 && $day <= 7) {
            $this->shifts[$day][] = ['', ''];
        }
    }

    public function removeShift(int $day, int $index): void
    {
        unset($this->shifts[$day][$index]);
        $this->shifts[$day] = array_values($this->shifts[$day] ?? []);
    }

    public function saveShifts(CalendarEditor $editor): void
    {
        $this->authorize('settings.manage');
        $version = $this->calendar->schedules()->findOrFail($this->versionId);
        if ($this->attempt(function () use ($editor, $version) {
            $editor->saveShifts($version, $this->shifts);

            return true;
        })) {
            $this->loadVersion($version->id);
            $this->dispatch('toast', message: __('worktime.edit.saved_hours'));
        }
    }

    public function createVersion(CalendarEditor $editor): void
    {
        $this->authorize('settings.manage');
        $version = $this->attempt(fn () => $editor->newVersion($this->calendar, $this->newVersionFrom), 'newVersionFrom');
        if ($version) {
            $this->newVersionFrom = '';
            $this->loadVersion($version->id);
        }
    }

    public function rename(CalendarEditor $editor): void
    {
        $this->authorize('settings.manage');
        if ($this->attempt(function () use ($editor) {
            $editor->rename($this->calendar, $this->name);

            return true;
        }, 'name')) {
            $this->dispatch('toast', message: __('worktime.assign.saved'));
        }
    }

    public function proposeHolidays(CalendarEditor $editor): void
    {
        $this->authorize('settings.manage');
        $n = $editor->proposeHolidays($this->calendar, $this->year);
        $this->dispatch('toast', message: trans_choice('worktime.edit.proposed', $n, ['count' => $n]));
    }

    public function addClosure(CalendarEditor $editor): void
    {
        $this->authorize('settings.manage');
        if ($this->attempt(fn () => $editor->addClosure($this->calendar, $this->closure['from'], $this->closure['to'] ?: $this->closure['from'], $this->closure['label']), 'closure.')) {
            $this->closure = ['from' => '', 'to' => '', 'label' => ''];
        }
    }

    public function setSpecial(CalendarEditor $editor): void
    {
        $this->authorize('settings.manage');
        if ($this->attempt(fn () => $editor->setDay($this->calendar, $this->special['date'], 'orario', $this->special['label'], [[$this->special['start'], $this->special['end']]]), 'special.')) {
            $this->special = ['date' => '', 'start' => '', 'end' => '', 'label' => ''];
        }
    }

    /** Festività tolta (si lavora) → di nuovo festa */
    public function restoreDay(int $id, CalendarEditor $editor): void
    {
        $this->authorize('settings.manage');
        $day = $this->day($id);
        $this->attempt(fn () => $editor->setDay($this->calendar, $day->date->toDateString(), 'chiuso', null), 'days');
    }

    public function removeDay(int $id, CalendarEditor $editor): void
    {
        $this->authorize('settings.manage');
        $day = $this->day($id);
        $this->attempt(function () use ($editor, $day) {
            $editor->removeDay($day);

            return true;
        }, 'days');
    }

    public function deleteCalendar(CalendarEditor $editor): RedirectResponse|Redirector|null
    {
        $this->authorize('settings.manage');
        if (! $this->attempt(function () use ($editor) {
            $editor->delete($this->calendar);

            return true;
        }, 'calendar')) {
            return null;
        }
        session()->flash('status', __('worktime.edit.deleted'));

        return redirect()->route('settings.worktime');
    }

    private function day(int $id): WorkCalendarException
    {
        return $this->calendar->exceptions()->findOrFail($id);
    }

    /**
     * Esegue un'azione del calendario riportando gli errori sul campo giusto.
     *
     * @template T
     *
     * @param  callable(): T  $action
     * @return T|null
     */
    private function attempt(callable $action, string $field = 'shifts'): mixed
    {
        $this->resetErrorBag();
        try {
            return $action();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $target = str_ends_with($field, '.') ? $field.$key : ($field === 'shifts' ? $key : $field);
                $this->addError($target, $messages[0]);
            }

            return null;
        }
    }

    public function render(): View
    {
        $versions = $this->calendar->schedules()->get();
        $version = $versions->firstWhere('id', $this->versionId);

        return view('livewire.settings.work-calendar-edit', [
            'versions' => $versions,
            'version' => $version,
            'editable' => $version?->isEditable() ?? false,
            'days' => $this->calendar->exceptions()->whereYear('date', $this->year)->get(),
            'years' => range((int) today()->year - 2, (int) today()->year + 2),
            'today' => today()->toDateString(),
        ])->title($this->calendar->name)->layoutData(['heading' => __('worktime.title')]);
    }
}
