<?php

namespace App\Enums;

enum WorkOrderType: string
{
    case Corrective = 'correttivo';
    case Preventive = 'preventivo';
    case Improvement = 'miglioramento';

    public function label(): string
    {
        return __('enums.work_order_type.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Corrective => 'wrench',
            self::Preventive => 'calendar',
            self::Improvement => 'sparkles',
        };
    }
}
