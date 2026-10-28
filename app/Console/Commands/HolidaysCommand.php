<?php

namespace App\Console\Commands;

use App\Models\WorkCalendar;
use App\Support\WorkTime\CalendarEditor;
use Illuminate\Console\Command;

/**
 * Propone le festività nazionali dell'anno in corso e del prossimo in tutti i calendari di
 * lavoro, senza toccare i giorni già decisi (comprese le festività tolte).
 */
class HolidaysCommand extends Command
{
    protected $signature = 'manutrack:festivita';

    protected $description = 'Propone le festività nazionali di quest\'anno e del prossimo nei calendari di lavoro';

    public function handle(CalendarEditor $editor): int
    {
        $year = (int) today()->year;
        $added = 0;
        foreach (WorkCalendar::query()->get() as $calendar) {
            $added += $editor->proposeHolidays($calendar, $year) + $editor->proposeHolidays($calendar, $year + 1);
        }
        $this->components->info(trans_choice('worktime.edit.proposed', $added, ['count' => $added]));

        return self::SUCCESS;
    }
}
