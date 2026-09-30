<?php

use App\Support\Tenancy\TenantBlueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            $table->string('number', 30);
            // incoming = collected from a customer; outgoing = paid to a supplier.
            $table->string('direction', 10);
            // Exactly one of these is set. Two real foreign keys (instead of a
            // polymorphic pair) keep same-company integrity in the database.
            $table->ulid('invoice_id')->nullable();
            $table->ulid('supplier_bill_id')->nullable();
            $table->string('method', 20);
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->date('paid_at');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'direction', 'paid_at']);
            $table->index(['company_id', 'invoice_id']);
            $table->index(['company_id', 'supplier_bill_id']);
            TenantBlueprint::foreign($table, 'invoice_id', 'invoices');
            TenantBlueprint::foreign($table, 'supplier_bill_id', 'supplier_bills');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
