<?php

use App\Support\Tenancy\TenantBlueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            $table->string('number', 30);
            $table->unsignedBigInteger('category_id');
            $table->ulid('supplier_id')->nullable();
            $table->string('description');
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->date('expense_date');
            $table->string('payment_method', 20)->nullable();
            // Private disk: receipts may contain personal or banking data.
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name')->nullable();
            // pending | approved | rejected
            $table->string('status', 20)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'expense_date']);
            $table->index(['company_id', 'category_id']);
            TenantBlueprint::foreign($table, 'category_id', 'expense_categories');
            TenantBlueprint::foreign($table, 'supplier_id', 'suppliers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
