<?php

namespace App\Enums;

/** Segnalazione: nuova → accettata (genera un ordine) | respinta | duplicata */
enum RequestStatus: string
{
    case New = 'nuova';
    case Accepted = 'accettata';
    case Rejected = 'respinta';
    case Duplicate = 'duplicata';

    public function label(): string
    {
        return __('enums.request_status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'warn',
            self::Accepted => 'ok',
            self::Rejected, self::Duplicate => 'stop',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::New => 'bell',
            self::Accepted => 'check-circle',
            self::Rejected => 'x-circle',
            self::Duplicate => 'layers',
        };
    }
}
