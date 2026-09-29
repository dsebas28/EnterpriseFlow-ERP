<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Schema\Blueprint;

/**
 * Schema helpers that keep tenant tables consistent.
 *
 * Every tenant-owned table gets a `company_id` column plus a composite
 * UNIQUE(company_id, id). The composite key lets child tables declare
 * FOREIGN KEY (company_id, x_id) REFERENCES x(company_id, id), so the
 * database itself rejects rows that point at another company's data.
 */
final class TenantBlueprint
{
    public static function company(Blueprint $table): void
    {
        $table->foreignUlid('company_id')->constrained()->restrictOnDelete();
        $table->unique(['company_id', 'id']);
    }

    /**
     * Declare a same-company foreign key. The column itself must already exist.
     */
    public static function foreign(
        Blueprint $table,
        string $column,
        string $on,
        string $onDelete = 'restrict',
    ): void {
        $table->foreign(['company_id', $column])
            ->references(['company_id', 'id'])
            ->on($on)
            ->onDelete($onDelete);
    }
}
