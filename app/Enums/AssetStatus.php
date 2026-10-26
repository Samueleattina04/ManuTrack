<?php

namespace App\Enums;

/**
 * Stato di una macchina. Colori andon, sempre con icona e testo.
 */
enum AssetStatus: string
{
    case Operational = 'operativa';
    case Fault = 'guasto';
    case Maintenance = 'manutenzione';
    case Stopped = 'ferma';
    case Retired = 'dismessa';

    public function label(): string
    {
        return __('enums.asset_status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Operational => 'ok',
            self::Fault => 'ko',
            self::Maintenance => 'mnt',
            self::Stopped, self::Retired => 'stop',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Operational => 'check-circle',
            self::Fault => 'alert-triangle',
            self::Maintenance => 'wrench',
            self::Stopped => 'pause-circle',
            self::Retired => 'x-circle',
        };
    }
}
