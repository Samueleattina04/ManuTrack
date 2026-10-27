<?php

namespace App\Enums;

/** Perché un ordine in corso è fermo in attesa */
enum WaitingReason: string
{
    case Parts = 'ricambi';
    case Supplier = 'fornitore';
    case Production = 'produzione';

    public function label(): string
    {
        return __('enums.waiting_reason.'.$this->value);
    }
}
