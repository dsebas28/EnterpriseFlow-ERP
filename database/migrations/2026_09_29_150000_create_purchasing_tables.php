<?php

use App\Support\Tenancy\TenantBlueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gap-free, per-company document numbering (PO-000001, GR-000001…).
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->string('type', 40);
            $table->string('prefix', 10);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'type']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            $table->string('kind', 20)->default('company'); // company | person
            $table->string('name');
            $table->string('tax_id', 50)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->char('country', 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'tax_id']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            $table->string('number', 30);
            $table->ulid('supplier_id');
            $table->ulid('warehouse_id');
            $table->string('status', 30)->default('draft');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            // Currency snapshot: documents keep the currency they were issued in.
            $table->char('currency', 3);
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'order_date']);
            $table->index(['company_id', 'supplier_id']);
            TenantBlueprint::foreign($table, 'supplier_id', 'suppliers');
            TenantBlueprint::foreign($table, 'warehouse_id', 'warehouses');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->ulid('purchase_order_id');
            $table->ulid('product_id');
            // Snapshots taken when the line is saved.
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->bigInteger('unit_cost');
            $table->unsignedInteger('tax_rate');
            $table->bigInteger('line_subtotal');
            $table->bigInteger('line_tax');
            $table->bigInteger('line_total');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['purchase_order_id', 'position']);
            TenantBlueprint::foreign($table, 'purchase_order_id', 'purchase_orders', onDelete: 'cascade');
            TenantBlueprint::foreign($table, 'product_id', 'products');
        });

        // Goods receipt: what physically arrived, possibly a part of the order.
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            $table->string('number', 30);
            $table->ulid('purchase_order_id');
            $table->ulid('warehouse_id');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            TenantBlueprint::foreign($table, 'purchase_order_id', 'purchase_orders');
            TenantBlueprint::foreign($table, 'warehouse_id', 'warehouses');
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->ulid('purchase_receipt_id');
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->ulid('product_id');
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_cost');
            $table->timestamps();

            TenantBlueprint::foreign($table, 'purchase_receipt_id', 'purchase_receipts', onDelete: 'cascade');
            TenantBlueprint::foreign($table, 'purchase_order_item_id', 'purchase_order_items');
            TenantBlueprint::foreign($table, 'product_id', 'products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('document_sequences');
    }
};
