<?php

use App\Support\Tenancy\TenantBlueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks asynchronous report exports so the UI can show their progress.
        Schema::create('report_exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report', 60);
            $table->string('format', 10);
            $table->json('filters');
            // pending | processing | completed | failed
            $table->string('status', 20)->default('pending');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('rows_count')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
