<?php

use App\Support\Tenancy\TenantBlueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only ledger: the source of truth for stock.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->ulid('product_id');
            $table->ulid('warehouse_id');
            $table->string('type', 20);
            // Signed: positive = stock in, negative = stock out. Never zero.
            $table->integer('quantity');
            // Running balance of (product, warehouse) right after this movement.
            $table->integer('balance_after');
            $table->bigInteger('unit_cost')->nullable();
            $table->string('reference_type', 40)->nullable();
            $table->string('reference_id', 26)->nullable();
            // Links the two legs of a transfer.
            $table->ulid('transfer_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'product_id', 'warehouse_id', 'id']);
            $table->index(['company_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
            TenantBlueprint::foreign($table, 'product_id', 'products');
            TenantBlueprint::foreign($table, 'warehouse_id', 'warehouses');
        });

        // Read-optimised projection of the ledger, updated in the same
        // transaction as each movement and rebuildable from it.
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->ulid('product_id');
            $table->ulid('warehouse_id');
            $table->integer('quantity')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['company_id', 'product_id', 'warehouse_id']);
            $table->index(['company_id', 'warehouse_id']);
            TenantBlueprint::foreign($table, 'product_id', 'products');
            TenantBlueprint::foreign($table, 'warehouse_id', 'warehouses');
        });

        $this->protectLedger();
    }

    public function down(): void
    {
        $this->unprotectLedger();

        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('stock_movements');
    }

    /**
     * Reject UPDATE and DELETE on the ledger at the database level, so not
     * even raw queries can rewrite stock history. Corrections are new
     * (compensating) movements.
     */
    private function protectLedger(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::unprepared(<<<'SQL'
                CREATE FUNCTION stock_movements_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'stock_movements is append-only: record a compensating movement instead';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER stock_movements_append_only
                    BEFORE UPDATE OR DELETE ON stock_movements
                    FOR EACH ROW EXECUTE FUNCTION stock_movements_append_only();
                SQL),
            'sqlite' => DB::unprepared(<<<'SQL'
                CREATE TRIGGER stock_movements_no_update BEFORE UPDATE ON stock_movements
                BEGIN
                    SELECT RAISE(ABORT, 'stock_movements is append-only: record a compensating movement instead');
                END;

                CREATE TRIGGER stock_movements_no_delete BEFORE DELETE ON stock_movements
                BEGIN
                    SELECT RAISE(ABORT, 'stock_movements is append-only: record a compensating movement instead');
                END;
                SQL),
            default => null,
        };
    }

    private function unprotectLedger(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS stock_movements_append_only ON stock_movements;
                DROP FUNCTION IF EXISTS stock_movements_append_only();
                SQL),
            'sqlite' => DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS stock_movements_no_update;
                DROP TRIGGER IF EXISTS stock_movements_no_delete;
                SQL),
            default => null,
        };
    }
};
