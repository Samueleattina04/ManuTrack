<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum IntervalUnit: string
{
    case Days = 'giorni';
    case Weeks = 'settimane';
    case Months = 'mesi';
    case Years = 'anni';

    public function label(int $count = 2): string
    {
        return trans_choice('enums.interval_unit.'.$this->value, $count);
    }

    /**
     * Aggiunge n intervalli. Mesi e anni senza "sforare": 31 gennaio + 1 mese = 28/29 febbraio.
     */
    public function add(CarbonImmutable $date, int $n): CarbonImmutable
    {
        return match ($this) {
            self::Days => $date->addDays($n),
            self::Weeks => $date->addWeeks($n),
            self::Months => $date->addMonthsNoOverflow($n),
            self::Years => $date->addYearsNoOverflow($n),
        };
    }
}
