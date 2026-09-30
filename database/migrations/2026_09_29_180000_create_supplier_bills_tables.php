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
        // How much of each purchase order line has already been billed by the supplier.
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->unsignedInteger('billed_quantity')->default(0)->after('received_quantity');
        });

        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            $table->string('number', 30);
            $table->ulid('supplier_id');
            $table->ulid('purchase_order_id');
            // The supplier's own invoice number.
            $table->string('supplier_reference', 60);
            $table->string('status', 30)->default('issued');
            $table->date('bill_date');
            $table->date('due_date');
            $table->char('currency', 3);
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('total')->default(0);
            $table->bigInteger('amount_paid')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'due_date']);
            TenantBlueprint::foreign($table, 'supplier_id', 'suppliers');
            TenantBlueprint::foreign($table, 'purchase_order_id', 'purchase_orders');
        });

        // The same supplier invoice can never be registered twice (unless voided).
        DB::statement("CREATE UNIQUE INDEX supplier_bills_unique_reference ON supplier_bills (company_id, supplier_id, supplier_reference) WHERE status <> 'cancelled'");

        Schema::create('supplier_bill_items', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->ulid('supplier_bill_id');
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->ulid('product_id');
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_cost');
            $table->unsignedInteger('tax_rate');
            $table->bigInteger('line_subtotal');
            $table->bigInteger('line_tax');
            $table->bigInteger('line_total');
            $table->timestamps();

            TenantBlueprint::foreign($table, 'supplier_bill_id', 'supplier_bills', onDelete: 'cascade');
            TenantBlueprint::foreign($table, 'purchase_order_item_id', 'purchase_order_items');
            TenantBlueprint::foreign($table, 'product_id', 'products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bill_items');
        DB::statement('DROP INDEX IF EXISTS supplier_bills_unique_reference');
        Schema::dropIfExists('supplier_bills');

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('billed_quantity');
        });
    }
};
