<?php

namespace App\Enums;

enum FailureCodeKind: string
{
    case Problem = 'problema';
    case Cause = 'causa';
    case Remedy = 'rimedio';

    public function label(): string
    {
        return __('enums.failure_kind.'.$this->value);
    }
}
