<?php

namespace App\Support\Money;

/**
 * ISO 4217 minor units for the currencies the product supports. Anything
 * not listed uses 2 decimals, which covers the vast majority of currencies.
 */
final class Currency
{
    /**
     * @var array<string, int>
     */
    private const MINOR_UNITS = [
        'BHD' => 3, 'CLP' => 0, 'ISK' => 0, 'JPY' => 0, 'KRW' => 0, 'KWD' => 3,
        'OMR' => 3, 'PYG' => 0, 'TND' => 3, 'UGX' => 0, 'VND' => 0, 'XAF' => 0, 'XOF' => 0,
    ];

    public static function minorUnits(string $currency): int
    {
        return self::MINOR_UNITS[strtoupper($currency)] ?? 2;
    }
}
