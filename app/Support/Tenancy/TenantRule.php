<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules restricted to the active company.
 *
 * An id that belongs to another company is reported as "invalid", exactly
 * like a non-existent id, so responses never leak other tenants' data.
 *
 * The company is resolved lazily, when the rule runs: building the rule
 * (e.g. while generating API docs) needs no tenant, but validating without
 * one still fails closed.
 */
final class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where(self::scope(...));
    }

    public static function unique(string $table, string $column = 'NULL'): Unique
    {
        return Rule::unique($table, $column)->where(self::scope(...));
    }

    private static function scope(Builder $query): void
    {
        $query->where('company_id', app(TenantContext::class)->idOrFail());
    }
}
