<?php

namespace App\Reports\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * SQL expression grouping a date column into day/week/month buckets.
 * Date functions differ per engine, so this is the one place that knows.
 */
final class DateBucket
{
    public static function sql(string $column, string $granularity): string
    {
        if (! preg_match('/^[a-z_.]+$/', $column)) {
            throw new InvalidArgumentException("Invalid column [{$column}].");
        }

        return match (DB::getDriverName()) {
            'pgsql' => match ($granularity) {
                'month' => "to_char({$column}, 'YYYY-MM')",
                'week' => "to_char({$column}, 'IYYY-\"W\"IW')",
                default => "to_char({$column}, 'YYYY-MM-DD')",
            },
            default => match ($granularity) {
                'month' => "strftime('%Y-%m', {$column})",
                'week' => "strftime('%Y-W%W', {$column})",
                default => "strftime('%Y-%m-%d', {$column})",
            },
        };
    }
}
