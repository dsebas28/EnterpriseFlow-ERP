<?php

namespace App\Support\Tenancy;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules restricted to the active company.
 *
 * An id that belongs to another company is reported as "invalid", exactly
 * like a non-existent id, so responses never leak other tenants' data.
 */
final class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where('company_id', app(TenantContext::class)->idOrFail());
    }

    public static function unique(string $table, string $column = 'NULL'): Unique
    {
        return Rule::unique($table, $column)
            ->where('company_id', app(TenantContext::class)->idOrFail());
    }
}
