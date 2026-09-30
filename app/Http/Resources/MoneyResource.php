<?php

namespace App\Http\Resources;

use App\Support\Money\Money;

/**
 * Canonical JSON shape for amounts across the web UI and the API:
 * integer minor units (exact), a decimal string (for inputs) and the
 * currency (for display formatting with Intl on the client).
 */
final class MoneyResource
{
    /**
     * @return array{amount: int, decimal: string, currency: string}
     */
    public static function make(Money $money): array
    {
        return [
            'amount' => $money->minor,
            'decimal' => $money->toDecimal(),
            'currency' => $money->currency,
        ];
    }
}
