<?php

namespace Tests\Fixtures;

use App\Support\Tenancy\TenantBlueprint;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal tenant-owned tables used to test the tenancy infrastructure in
 * isolation from any business module.
 */
final class TenantFixtures
{
    public static function migrate(): void
    {
        Schema::create('fixture_projects', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('fixture_tasks', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->unsignedBigInteger('project_id');
            $table->string('title');
            $table->timestamps();

            TenantBlueprint::foreign($table, 'project_id', 'fixture_projects');
        });
    }
}
