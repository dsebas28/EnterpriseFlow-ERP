<?php

use App\Support\Tenancy\TenantBlueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('description')->nullable();
            // Provisioned from App\Enums\SystemRole; cannot be deleted.
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        // Permission names come from App\Enums\Permission (code is the source
        // of truth), so there is no permissions table to keep in sync.
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 100);

            $table->primary(['role_id', 'permission']);
            $table->index('permission');
        });

        Schema::create('membership_role', function (Blueprint $table) {
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->primary(['membership_id', 'role_id']);

            // Both sides must belong to the same company: a member can never
            // hold a role defined by another tenant.
            $table->foreign(['company_id', 'membership_id'])
                ->references(['company_id', 'id'])->on('company_user')->cascadeOnDelete();
            $table->foreign(['company_id', 'role_id'])
                ->references(['company_id', 'id'])->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_role');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
