<?php

namespace App\Enums;

/**
 * Priorità di ordini di lavoro e segnalazioni.
 */
enum Priority: string
{
    case Low = 'bassa';
    case Medium = 'media';
    case High = 'alta';
    case Critical = 'critica';

    public function label(): string
    {
        return __('enums.priority.'.$this->value);
    }

    /** Livello da 1 a 4, mostrato come barre */
    public function level(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Low => 'stop',
            self::Medium => 'mnt',
            self::High => 'warn',
            self::Critical => 'ko',
        };
    }
}
