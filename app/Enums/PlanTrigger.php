<?php

namespace App\Enums;

/** Cosa fa scattare un piano preventivo */
enum PlanTrigger: string
{
    case Time = 'tempo';
    case Meter = 'contatore';

    public function label(): string
    {
        return __('enums.plan_trigger.'.$this->value);
    }

    public function icon(): string
    {
        return $this === self::Time ? 'calendar' : 'gauge';
    }
}
