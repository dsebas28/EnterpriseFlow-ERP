<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Posted = 'posted';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Posted => 'Posted',
            self::Voided => 'Voided',
        };
    }
}
