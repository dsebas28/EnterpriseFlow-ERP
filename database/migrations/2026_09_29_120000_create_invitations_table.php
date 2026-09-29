<?php

use App\Support\Tenancy\TenantBlueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            $table->string('email');
            $table->unsignedBigInteger('role_id');
            // SHA-256 of the token; the plain token only ever exists in the email.
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'email']);
            TenantBlueprint::foreign($table, 'role_id', 'roles', onDelete: 'cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
