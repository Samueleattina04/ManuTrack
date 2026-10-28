<?php

namespace App\Support\WorkTime;

use App\Models\Location;
use App\Models\User;
use App\Models\WorkCalendar;
use App\Models\WorkCalendarAssignment;
use App\Models\WorkCalendarException;
use App\Models\WorkCalendarSchedule;
use App\Support\Access\Access;
use App\Support\Audit\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Modifiche ai calendari di lavoro, con le regole che tengono fermi i mesi passati:
 * - un orario settimanale già cominciato non si modifica (salvo il giorno in cui è stato creato):
 *   si crea una versione nuova "valida dal";
 * - le eccezioni dei giorni passati non si toccano;
 * - ogni assegnazione ha la sua data "valido dal".
 */
final class CalendarEditor
{
    /** Inizio della prima versione: il calendario vale da quando lo si assegna */
    public const FIRST_DAY = '2000-01-01';

    /** @var array<string, array<int, list<array{0: string, 1: string}>>> */
    public const PRESETS = [
        'un_turno' => [1 => [['08:00', '12:00'], ['13:00', '17:00']], 2 => [['08:00', '12:00'], ['13:00', '17:00']], 3 => [['08:00', '12:00'], ['13:00', '17:00']], 4 => [['08:00', '12:00'], ['13:00', '17:00']], 5 => [['08:00', '12:00'], ['13:00', '17:00']]],
        'due_turni' => [1 => [['06:00', '14:00'], ['14:00', '22:00']], 2 => [['06:00', '14:00'], ['14:00', '22:00']], 3 => [['06:00', '14:00'], ['14:00', '22:00']], 4 => [['06:00', '14:00'], ['14:00', '22:00']], 5 => [['06:00', '14:00'], ['14:00', '22:00']]],
        'tre_turni' => [1 => [['06:00', '14:00'], ['14:00', '22:00'], ['22:00', '06:00']], 2 => [['06:00', '14:00'], ['14:00', '22:00'], ['22:00', '06:00']], 3 => [['06:00', '14:00'], ['14:00', '22:00'], ['22:00', '06:00']], 4 => [['06:00', '14:00'], ['14:00', '22:00'], ['22:00', '06:00']], 5 => [['06:00', '14:00'], ['14:00', '22:00'], ['22:00', '06:00']]],
        'continuo' => [1 => [['00:00', '00:00']], 2 => [['00:00', '00:00']], 3 => [['00:00', '00:00']], 4 => [['00:00', '00:00']], 5 => [['00:00', '00:00']], 6 => [['00:00', '00:00']], 7 => [['00:00', '00:00']]],
        'vuoto' => [],
    ];

    public function create(string $name, string $preset): WorkCalendar
    {
        $name = trim($name);
        if ($name === '' || WorkCalendar::query()->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => __('worktime.errors.name')]);
        }
        $shifts = self::PRESETS[$preset] ?? self::PRESETS['vuoto'];

        return DB::transaction(function () use ($name, $shifts) {
            $calendar = WorkCalendar::query()->create(['name' => $name]);
            $schedule = $calendar->schedules()->create(['valid_from' => self::FIRST_DAY]);
            $this->writeShifts($schedule, $shifts);
            $year = (int) today()->year;
            $this->proposeHolidays($calendar, $year);
            $this->proposeHolidays($calendar, $year + 1);
            Audit::log('settings.work_calendar_created', $calendar, $calendar->name);

            return $calendar;
        });
    }

    public function rename(WorkCalendar $calendar, string $name): void
    {
        $name = trim($name);
        if ($name === '' || WorkCalendar::query()->where('name', $name)->whereKeyNot($calendar->id)->exists()) {
            throw ValidationException::withMessages(['name' => __('worktime.errors.name')]);
        }
        $calendar->update(['name' => $name]);
    }

    /** Un calendario mai assegnato si può eliminare; uno usato no (servirebbe a ricalcolare il passato) */
    public function delete(WorkCalendar $calendar): void
    {
        if ($calendar->assignments()->exists()) {
            throw ValidationException::withMessages(['calendar' => __('worktime.errors.in_use')]);
        }
        Audit::log('settings.work_calendar_deleted', $calendar, $calendar->name);
        $calendar->delete();
    }

    /**
     * Turni della versione: giorno (1-7) => [[inizio, fine], ...]
     *
     * @param  array<int|string, list<array{0: string, 1: string}>>  $shifts
     */
    public function saveShifts(WorkCalendarSchedule $schedule, array $shifts): void
    {
        if (! $schedule->isEditable()) {
            throw ValidationException::withMessages(['shifts' => __('worktime.errors.started')]);
        }
        DB::transaction(function () use ($schedule, $shifts) {
            $this->writeShifts($schedule, $shifts);
            Audit::log('settings.work_calendar_hours', $schedule->calendar, $schedule->calendar->name.' · '.$schedule->valid_from->toDateString());
        });
    }

    /** Nuova versione dell'orario da una data (oggi o dopo), che parte copiando quella in vigore */
    public function newVersion(WorkCalendar $calendar, string $validFrom): WorkCalendarSchedule
    {
        $date = self::date($validFrom, 'valid_from');
        if ($date->lt(today()->toImmutable())) {
            throw ValidationException::withMessages(['valid_from' => __('worktime.errors.past')]);
        }
        $latest = $calendar->schedules()->with('shifts')->reorder('valid_from', 'desc')->first();
        if ($latest && ! $date->gt(CarbonImmutable::parse($latest->valid_from->toDateString()))) {
            throw ValidationException::withMessages(['valid_from' => __('worktime.errors.after_latest', ['date' => $latest->valid_from->translatedFormat('j M Y')])]);
        }

        return DB::transaction(function () use ($calendar, $date, $latest) {
            $schedule = $calendar->schedules()->create(['valid_from' => $date->toDateString()]);
            foreach ($latest === null ? [] : $latest->shifts as $shift) {
                $schedule->shifts()->create(['weekday' => $shift->weekday, 'start' => $shift->start, 'end' => $shift->end]);
            }
            Audit::log('settings.work_calendar_version', $calendar, $calendar->name.' · '.$date->toDateString());

            return $schedule;
        });
    }

    /** Propone le festività nazionali dell'anno (senza toccare i giorni già decisi) */
    public function proposeHolidays(WorkCalendar $calendar, int $year): int
    {
        $existing = $calendar->exceptions()->whereYear('date', $year)->pluck('date')->map(fn ($d) => $d->toDateString())->all();
        $added = 0;
        foreach (Holidays::italian($year) as $date => $label) {
            if (! in_array($date, $existing, true)) {
                $calendar->exceptions()->create(['date' => $date, 'kind' => 'chiuso', 'label' => $label, 'source' => 'festivita']);
                $added++;
            }
        }

        return $added;
    }

    /**
     * Giorno diverso dal solito (oggi o dopo).
     *
     * @param  list<array{0: string, 1: string}>  $hours  per "orario"
     */
    public function setDay(WorkCalendar $calendar, string $date, string $kind, ?string $label, array $hours = []): WorkCalendarException
    {
        $day = self::date($date, 'date');
        if ($day->lt(today()->toImmutable())) {
            throw ValidationException::withMessages(['date' => __('worktime.errors.past_day')]);
        }
        if (! in_array($kind, WorkCalendarException::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => __('worktime.errors.kind')]);
        }
        if ($kind === 'orario') {
            $hours = $this->validHours($hours, 'hours');
            if ($hours === []) {
                throw ValidationException::withMessages(['hours' => __('worktime.errors.hours_required')]);
            }
        }
        $existing = $calendar->exceptions()->whereDate('date', $day->toDateString())->first();
        $exception = $existing ?? new WorkCalendarException(['date' => $day->toDateString(), 'source' => 'manuale']);
        $exception->fill([
            'kind' => $kind,
            'label' => $label !== null && trim($label) !== '' ? mb_substr(trim($label), 0, 80) : $exception->label,
            'hours' => $kind === 'orario' ? $hours : null,
        ]);
        $calendar->exceptions()->save($exception);
        Audit::log('settings.work_calendar_day', $calendar, $calendar->name.' · '.$day->toDateString().' · '.$kind);

        return $exception;
    }

    /** Chiusura aziendale su più giorni (es. ferie di agosto) */
    public function addClosure(WorkCalendar $calendar, string $from, string $to, string $label): int
    {
        $start = self::date($from, 'from');
        $end = self::date($to, 'to');
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['to' => __('worktime.errors.order')]);
        }
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['to' => __('worktime.errors.too_long')]);
        }
        if (trim($label) === '') {
            throw ValidationException::withMessages(['label' => __('worktime.errors.label')]);
        }
        $n = 0;
        DB::transaction(function () use ($calendar, $start, $end, $label, &$n) {
            for ($d = $start; $d->lte($end); $d = $d->addDay()) {
                $this->setDay($calendar, $d->toDateString(), 'chiuso', $label);
                $n++;
            }
        });

        return $n;
    }

    /** Toglie un giorno speciale: una festività proposta diventa "si lavora", le altre spariscono */
    public function removeDay(WorkCalendarException $exception): void
    {
        if (! $exception->isEditable()) {
            throw ValidationException::withMessages(['date' => __('worktime.errors.past_day')]);
        }
        Audit::log('settings.work_calendar_day', $exception->calendar, $exception->calendar->name.' · '.$exception->date->toDateString().' · tolto');
        if ($exception->source === 'festivita') {
            $exception->update(['kind' => 'lavorativo', 'hours' => null]);
        } else {
            $exception->delete();
        }
    }

    /**
     * Assegna un calendario (null = eredita / 24 ore su 24) all'azienda o a un'ubicazione da una data.
     * L'assegnazione alla singola macchina è prevista dal calcolo ma non dalle pagine.
     */
    public function assign(string $scope, ?Location $location, ?WorkCalendar $calendar, string $validFrom, ?int $assetId = null, ?User $actor = null, bool $confirmPast = false): WorkCalendarAssignment
    {
        if (! in_array($scope, WorkCalendarAssignment::SCOPES, true) || ($scope === 'ubicazione' && ! $location) || ($scope === 'macchina' && ! $assetId)) {
            throw ValidationException::withMessages(['scope' => __('worktime.errors.scope')]);
        }
        $date = self::date($validFrom, 'valid_from');
        // Data passata: cambia gli indicatori già calcolati. Solo l'amministratore (o un comando
        // dal terminale, es. i dati dimostrativi), con conferma esplicita; sempre nel registro.
        $past = $date->lt(today()->toImmutable());
        if ($past) {
            $actor ??= auth()->user();
            if ($actor ? ! $actor->hasRole(Access::ADMIN) : ! app()->runningInConsole()) {
                throw ValidationException::withMessages(['valid_from' => __('worktime.errors.past_admin_only')]);
            }
            if (! $confirmPast) {
                throw ValidationException::withMessages(['valid_from' => __('worktime.errors.past_confirm', ['months' => self::affectedMonths($date)])]);
            }
        }
        $row = WorkCalendarAssignment::query()
            ->where('scope', $scope)
            ->where('location_id', $scope === 'ubicazione' ? $location->id : null)
            ->where('asset_id', $scope === 'macchina' ? $assetId : null)
            ->whereDate('valid_from', $date->toDateString())
            ->first() ?? new WorkCalendarAssignment([
                'scope' => $scope,
                'location_id' => $scope === 'ubicazione' ? $location->id : null,
                'asset_id' => $scope === 'macchina' ? $assetId : null,
                'valid_from' => $date->toDateString(),
            ]);
        $row->work_calendar_id = $calendar?->id;
        $row->save();
        Audit::log(
            $past ? 'settings.work_calendar_assigned_past' : 'settings.work_calendar_assigned',
            $calendar,
            ($scope === 'ubicazione' ? $location->name : __('worktime.scope.'.$scope)).' · '.($calendar->name ?? __('worktime.inherit')).' · '.$date->toDateString(),
            $past ? ['valido_dal' => $date->toDateString(), 'mesi_degli_indicatori_che_cambiano' => self::affectedMonths($date)] : [],
            $actor,
        );

        return $row;
    }

    /** Mesi degli indicatori che cambiano con un'assegnazione da una data passata, es. "marzo 2026 – ottobre 2026 (8 mesi)" */
    public static function affectedMonths(CarbonImmutable $from): string
    {
        $first = $from->startOfMonth();
        $last = today()->toImmutable()->startOfMonth();
        $n = (int) $first->diffInMonths($last) + 1;

        return trans_choice('worktime.months_range', $n, ['from' => $first->translatedFormat('F Y'), 'to' => $last->translatedFormat('F Y'), 'n' => $n]);
    }

    /**
     * @param  array<int|string, mixed>  $shifts
     */
    private function writeShifts(WorkCalendarSchedule $schedule, array $shifts): void
    {
        $clean = [];
        foreach ($shifts as $weekday => $list) {
            $weekday = (int) $weekday;
            if ($weekday < 1 || $weekday > 7) {
                continue;
            }
            foreach ($this->validHours((array) $list, 'shifts.'.$weekday) as [$start, $end]) {
                $clean[] = ['weekday' => $weekday, 'start' => $start, 'end' => $end];
            }
        }
        $schedule->shifts()->delete();
        foreach ($clean as $row) {
            $schedule->shifts()->create($row);
        }
    }

    /**
     * Orari "HH:MM" validi, senza sovrapposizioni nello stesso giorno (un turno che passa la
     * mezzanotte va fino alle 24 per il controllo).
     *
     * @param  array<int, mixed>  $hours
     * @return list<array{0: string, 1: string}>
     */
    private function validHours(array $hours, string $field): array
    {
        $out = [];
        foreach ($hours as $pair) {
            $pair = array_values((array) $pair);
            $start = trim((string) ($pair[0] ?? ''));
            $end = trim((string) ($pair[1] ?? ''));
            if ($start === '' && $end === '') {
                continue;
            }
            foreach ([$start, $end] as $t) {
                if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) {
                    throw ValidationException::withMessages([$field => __('worktime.errors.time', ['value' => $t])]);
                }
            }
            $out[] = [$start, $end];
        }
        usort($out, fn ($a, $b) => strcmp($a[0], $b[0]));
        $minutes = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        for ($i = 1; $i < count($out); $i++) {
            $prevEnd = $out[$i - 1][1] <= $out[$i - 1][0] ? 1440 : $minutes($out[$i - 1][1]);
            if ($minutes($out[$i][0]) < $prevEnd) {
                throw ValidationException::withMessages([$field => __('worktime.errors.overlap', ['a' => implode('–', $out[$i - 1]), 'b' => implode('–', $out[$i])])]);
            }
        }
        if (count($out) > 1 && collect($out)->contains(fn ($p) => $p[1] <= $p[0] && $p !== end($out))) {
            throw ValidationException::withMessages([$field => __('worktime.errors.night_last')]);
        }

        return $out;
    }

    private static function date(string $value, string $field): CarbonImmutable
    {
        $d = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        if (! $d || $d->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => __('worktime.errors.date')]);
        }

        return $d;
    }
}
