<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Nullable: a few events (e.g. platform actions) have no company.
            $table->foreignUlid('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // created | updated | deleted | restored | domain events such as role.permissions_changed
            $table->string('event', 60);
            $table->string('auditable_type', 40);
            $table->string('auditable_id', 26);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'auditable_type', 'auditable_id']);
            $table->index(['company_id', 'user_id']);
        });

        $this->protect();
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs; DROP FUNCTION IF EXISTS audit_logs_append_only();'),
            'sqlite' => DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update; DROP TRIGGER IF EXISTS audit_logs_no_delete;'),
            default => null,
        };

        Schema::dropIfExists('audit_logs');
    }

    /**
     * An audit trail that can be edited proves nothing: reject UPDATE and
     * DELETE at the database level, for raw SQL too.
     */
    private function protect(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::unprepared(<<<'SQL'
                -- Functions outlive `migrate:fresh` (it only drops tables), so
                -- both statements must be re-runnable.
                CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_logs is append-only';
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs;
                CREATE TRIGGER audit_logs_append_only
                    BEFORE UPDATE OR DELETE ON audit_logs
                    FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only();
                SQL),
            'sqlite' => DB::unprepared(<<<'SQL'
                CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs
                BEGIN SELECT RAISE(ABORT, 'audit_logs is append-only'); END;

                CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs
                BEGIN SELECT RAISE(ABORT, 'audit_logs is append-only'); END;
                SQL),
            default => null,
        };
    }
};
