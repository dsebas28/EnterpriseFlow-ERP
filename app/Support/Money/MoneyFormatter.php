<?php

namespace App\Support\Money;

use NumberFormatter;

/**
 * Server-side display formatting (PDFs, emails). The web UI formats on the
 * client with Intl; both only render values already computed as integers.
 */
final class MoneyFormatter
{
    public static function format(Money $money, string $locale = 'en'): string
    {
        $decimal = $money->toDecimal();

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency((float) $decimal, $money->currency);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        $decimals = Currency::minorUnits($money->currency);

        return $money->currency.' '.number_format((float) $decimal, $decimals);
    }
}
