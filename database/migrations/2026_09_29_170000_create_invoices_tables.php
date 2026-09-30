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
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            // Assigned when the invoice is issued; drafts have no legal number.
            $table->string('number', 30)->nullable();
            $table->ulid('sale_id');
            $table->ulid('customer_id');
            $table->string('status', 30)->default('draft');
            $table->date('issue_date')->nullable();
            $table->date('due_date');
            $table->char('currency', 3);
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('total')->default(0);
            $table->bigInteger('amount_paid')->default(0);
            $table->text('notes')->nullable();
            $table->string('pdf_status', 20)->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'due_date']);
            $table->index(['company_id', 'customer_id']);
            TenantBlueprint::foreign($table, 'sale_id', 'sales');
            TenantBlueprint::foreign($table, 'customer_id', 'customers');
        });

        // A sale has at most one invoice that is not cancelled.
        DB::statement("CREATE UNIQUE INDEX invoices_one_active_per_sale ON invoices (company_id, sale_id) WHERE status <> 'cancelled'");

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->ulid('invoice_id');
            $table->ulid('product_id');
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price');
            $table->unsignedInteger('discount_rate')->default(0);
            $table->unsignedInteger('tax_rate');
            $table->bigInteger('line_discount');
            $table->bigInteger('line_subtotal');
            $table->bigInteger('line_tax');
            $table->bigInteger('line_total');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            TenantBlueprint::foreign($table, 'invoice_id', 'invoices', onDelete: 'cascade');
            TenantBlueprint::foreign($table, 'product_id', 'products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        DB::statement('DROP INDEX IF EXISTS invoices_one_active_per_sale');
        Schema::dropIfExists('invoices');
    }
};
