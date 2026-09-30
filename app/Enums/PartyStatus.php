<?php

namespace App\Enums;

/**
 * Status of business partners (suppliers, customers).
 */
enum PartyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
