<?php

use App\Support\Tenancy\TenantBlueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            $table->string('kind', 20)->default('company'); // company | person
            $table->string('name');
            $table->string('tax_id', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'tax_id']);
            $table->index(['company_id', 'name']);
        });

        // Internal notes about a customer (calls, agreements, reminders).
        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->ulid('customer_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            TenantBlueprint::foreign($table, 'customer_id', 'customers', onDelete: 'cascade');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            $table->string('number', 30);
            $table->ulid('customer_id');
            $table->ulid('warehouse_id');
            $table->string('status', 30)->default('draft');
            $table->date('sale_date');
            $table->char('currency', 3);
            // subtotal is net of discounts; total = subtotal + tax.
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('total')->default(0);
            // Maintained by the payments module; balance due = total - amount_paid.
            $table->bigInteger('amount_paid')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'sale_date']);
            $table->index(['company_id', 'customer_id']);
            TenantBlueprint::foreign($table, 'customer_id', 'customers');
            TenantBlueprint::foreign($table, 'warehouse_id', 'warehouses');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->ulid('sale_id');
            $table->ulid('product_id');
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price');
            $table->unsignedInteger('discount_rate')->default(0);
            $table->unsignedInteger('tax_rate');
            // Cost snapshot taken at confirmation, for margin reporting.
            $table->bigInteger('unit_cost')->nullable();
            $table->bigInteger('line_discount');
            $table->bigInteger('line_subtotal');
            $table->bigInteger('line_tax');
            $table->bigInteger('line_total');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['sale_id', 'position']);
            $table->index(['company_id', 'product_id']);
            TenantBlueprint::foreign($table, 'sale_id', 'sales', onDelete: 'cascade');
            TenantBlueprint::foreign($table, 'product_id', 'products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customers');
    }
};
