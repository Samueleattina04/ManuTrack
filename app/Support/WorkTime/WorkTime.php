<?php

namespace App\Support\WorkTime;

use App\Models\Asset;
use App\Models\WorkCalendarAssignment;
use App\Models\WorkCalendarException;
use App\Models\WorkCalendarSchedule;
use Carbon\CarbonImmutable;

/**
 * Tempo in cui una macchina dovrebbe lavorare (docs/KPI.md §2.9).
 *
 * - Il calendario di una macchina in un giorno è quello assegnato al livello più specifico
 *   (macchina, poi ubicazione e quelle che la contengono, poi azienda) con "valido dal" non
 *   successivo a quel giorno; un'assegnazione vuota eredita dal livello sopra. Senza nessun
 *   calendario la macchina lavora 24 ore su 24.
 * - In un giorno valgono, nell'ordine: l'eccezione di quel giorno (chiuso, orario speciale,
 *   lavorativo) e l'orario settimanale della versione in vigore quel giorno.
 * - Un turno appartiene al giorno in cui comincia: 22:00–06:00 di venerdì finisce sabato,
 *   anche se sabato è festa. Le durate sono sul tempo reale trascorso (ora legale compresa).
 *
 * I dati dei calendari si leggono una volta sola per istanza.
 */
final class WorkTime
{
    /** @var array<int, array{schedules: list<array{from: string, shifts: array<int, list<array{0: string, 1: string}>>}>, exceptions: array<string, array{kind: string, hours: list<array{0: string, 1: string}>}>}>|null */
    private ?array $calendars = null;

    /** @var array<string, list<array{from: string, calendar: int|null}>>|null livello => righe ordinate per data */
    private ?array $assignments = null;

    /** @var array<string, list<array{0: int, 1: int}>> */
    private array $dayCache = [];

    private string $tz;

    public function __construct()
    {
        $this->tz = (string) config('app.timezone');
    }

    /** C'è almeno un calendario assegnato da qualche parte (altrimenti tutto resta 24 ore su 24) */
    public function enabled(): bool
    {
        $this->load();

        foreach ($this->assignments as $rows) {
            foreach ($rows as $row) {
                if ($row['calendar'] !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Calendario della macchina in un giorno (null = 24 ore su 24).
     *
     * @param  list<int>  $locationChain  ubicazioni dalla più vicina alla più lontana
     */
    public function calendarFor(int $assetId, array $locationChain, string $date): ?int
    {
        $this->load();
        $levels = ['macchina:'.$assetId];
        foreach ($locationChain as $id) {
            $levels[] = 'ubicazione:'.$id;
        }
        $levels[] = 'azienda:';

        foreach ($levels as $level) {
            $active = null;
            foreach ($this->assignments[$level] ?? [] as $row) {
                if ($row['from'] > $date) {
                    break;
                }
                $active = $row;
            }
            if ($active !== null && $active['calendar'] !== null) {
                return $active['calendar'];
            }
        }

        return null;
    }

    /**
     * Intervalli di lavoro della macchina dentro [from, to), uniti, e quanti giorni del
     * periodo erano coperti da un calendario.
     *
     * @return array{intervals: list<array{0: int, 1: int}>, calendar_days: int, days: int}
     */
    public function forAsset(Asset $asset, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $chain = self::locationChain($asset);
        $intervals = [];
        $calendarDays = 0;
        $days = 0;
        $firstDay = $from->setTimezone($this->tz)->startOfDay();
        // Dal giorno prima: i turni di notte che cominciano il giorno prima finiscono nel periodo
        for ($day = $firstDay->subDay(); $day->lt($to); $day = $day->addDay()) {
            $date = $day->toDateString();
            $calendar = $this->calendarFor($asset->id, $chain, $date);
            if ($day->gte($firstDay)) {
                $days++;
                $calendarDays += $calendar !== null ? 1 : 0;
            }
            if ($calendar === null) {
                $intervals[] = [$day->getTimestamp(), $day->addDay()->getTimestamp()];
            } else {
                array_push($intervals, ...$this->dayIntervals($calendar, $date));
            }
        }

        return [
            'intervals' => Intervals::merge(Intervals::clip($intervals, $from->getTimestamp(), $to->getTimestamp())),
            'calendar_days' => $calendarDays,
            'days' => $days,
        ];
    }

    /**
     * Turni che cominciano in un giorno, per un calendario.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function dayIntervals(int $calendar, string $date): array
    {
        $key = $calendar.'@'.$date;
        if (isset($this->dayCache[$key])) {
            return $this->dayCache[$key];
        }
        $this->load();
        $data = $this->calendars[$calendar] ?? null;
        $shifts = [];
        if ($data !== null) {
            $exception = $data['exceptions'][$date] ?? null;
            if ($exception !== null && $exception['kind'] === 'chiuso') {
                $shifts = [];
            } elseif ($exception !== null && $exception['kind'] === 'orario') {
                $shifts = $exception['hours'];
            } else {
                $version = null;
                foreach ($data['schedules'] as $schedule) {
                    if ($schedule['from'] > $date) {
                        break;
                    }
                    $version = $schedule;
                }
                $weekday = (int) CarbonImmutable::parse($date, $this->tz)->isoWeekday();
                $shifts = $version['shifts'][$weekday] ?? [];
            }
        }

        $out = [];
        foreach ($shifts as [$start, $end]) {
            $s = CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.$start, $this->tz);
            $e = CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.$end, $this->tz);
            if (! $s || ! $e) {
                continue;
            }
            if ($end <= $start) {
                $e = CarbonImmutable::createFromFormat('Y-m-d H:i', CarbonImmutable::parse($date, $this->tz)->addDay()->toDateString().' '.$end, $this->tz) ?: $e;
            }
            $out[] = [$s->getTimestamp(), $e->getTimestamp()];
        }

        return $this->dayCache[$key] = Intervals::merge($out);
    }

    /**
     * Ubicazioni dalla più vicina alla più lontana (dal percorso "/1/4/9/").
     *
     * @return list<int>
     */
    public static function locationChain(Asset $asset): array
    {
        $path = $asset->location?->path;
        if ($path === null) {
            return [];
        }

        return array_reverse(array_map('intval', array_values(array_filter(explode('/', $path), fn ($p) => $p !== ''))));
    }

    private function load(): void
    {
        if ($this->calendars !== null) {
            return;
        }
        $this->calendars = [];
        foreach (WorkCalendarSchedule::query()->with('shifts')->orderBy('valid_from')->get() as $schedule) {
            $byDay = [];
            foreach ($schedule->shifts as $shift) {
                $byDay[$shift->weekday][] = [$shift->start, $shift->end];
            }
            $this->calendars[$schedule->work_calendar_id]['schedules'][] = ['from' => $schedule->valid_from->toDateString(), 'shifts' => $byDay];
            $this->calendars[$schedule->work_calendar_id]['exceptions'] ??= [];
        }
        foreach (WorkCalendarException::query()->get() as $ex) {
            $this->calendars[$ex->work_calendar_id]['schedules'] ??= [];
            $this->calendars[$ex->work_calendar_id]['exceptions'][$ex->date->toDateString()] = [
                'kind' => $ex->kind,
                'hours' => array_map(fn ($h) => [(string) $h[0], (string) $h[1]], $ex->hours ?? []),
            ];
        }

        $this->assignments = [];
        foreach (WorkCalendarAssignment::query()->orderBy('valid_from')->orderBy('id')->get() as $a) {
            $level = match ($a->scope) {
                'macchina' => 'macchina:'.$a->asset_id,
                'ubicazione' => 'ubicazione:'.$a->location_id,
                default => 'azienda:',
            };
            $this->assignments[$level][] = ['from' => $a->valid_from->toDateString(), 'calendar' => $a->work_calendar_id];
        }
    }
}
