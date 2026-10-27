<?php

namespace App\Enums;

/** Tipo di movimento di magazzino */
enum MovementType: string
{
    case Receipt = 'carico';
    case Issue = 'prelievo';
    case Return = 'reso';
    case Adjustment = 'rettifica';
    case Transfer = 'trasferimento';

    public function label(): string
    {
        return __('enums.movement_type.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Receipt => 'download',
            self::Issue => 'upload',
            self::Return => 'refresh',
            self::Adjustment => 'pencil',
            self::Transfer => 'arrow-right',
        };
    }
}
