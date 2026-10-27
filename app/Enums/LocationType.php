<?php

namespace App\Enums;

/**
 * Livello di un'ubicazione: sito > area > linea.
 */
enum LocationType: string
{
    case Site = 'sito';
    case Area = 'area';
    case Line = 'linea';

    public static function forDepth(int $depth): self
    {
        return match ($depth) {
            0 => self::Site,
            1 => self::Area,
            default => self::Line,
        };
    }

    public function depth(): int
    {
        return match ($this) {
            self::Site => 0,
            self::Area => 1,
            self::Line => 2,
        };
    }

    public function label(): string
    {
        return __('enums.location_type.'.$this->value);
    }

    public function child(): ?self
    {
        return match ($this) {
            self::Site => self::Area,
            self::Area => self::Line,
            self::Line => null,
        };
    }
}
