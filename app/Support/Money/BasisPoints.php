<?php

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * Converts percentages to basis points without floats ("19.5" => 1950).
 */
final class BasisPoints
{
    public static function fromPercent(string $percent): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($percent), $parts)) {
            throw new InvalidArgumentException("Invalid percentage [{$percent}].");
        }

        return (int) ($parts[1].str_pad($parts[2] ?? '', 2, '0'));
    }

    public static function toPercent(int $basisPoints): string
    {
        return intdiv($basisPoints, 100).'.'.str_pad((string) ($basisPoints % 100), 2, '0', STR_PAD_LEFT);
    }
}
