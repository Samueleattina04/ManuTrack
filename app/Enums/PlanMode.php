<?php

namespace App\Enums;

/**
 * Da dove si conta la scadenza successiva:
 * - fisso: dal calendario del piano (ogni 1° del mese resta il 1° del mese);
 * - ultima: dall'ultima esecuzione (3 mesi dopo l'ultimo intervento).
 */
enum PlanMode: string
{
    case Fixed = 'fisso';
    case FromLast = 'ultima';

    public function label(): string
    {
        return __('enums.plan_mode.'.$this->value);
    }
}
