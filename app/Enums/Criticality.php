<?php

namespace App\Enums;

/**
 * Criticità di una macchina per la produzione.
 * A: se si ferma, si ferma la linea. B: rallenta la produzione. C: non la blocca.
 */
enum Criticality: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';

    public function label(): string
    {
        return __('enums.criticality.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::A => 'ko',
            self::B => 'warn',
            self::C => 'stop',
        };
    }
}
