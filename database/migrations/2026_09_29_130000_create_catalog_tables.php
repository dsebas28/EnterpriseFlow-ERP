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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });

        // At most one default warehouse per company, enforced by the database.
        // Partial indexes are supported by both PostgreSQL and SQLite.
        DB::statement(
            'CREATE UNIQUE INDEX warehouses_one_default_per_company ON warehouses (company_id) WHERE is_default = true AND deleted_at IS NULL'
        );

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
            TenantBlueprint::foreign($table, 'parent_id', 'categories');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            TenantBlueprint::company($table);
            // simple | variable (template, not stockable) | variant (child of a variable product)
            $table->string('type', 20)->default('simple');
            $table->ulid('parent_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('sku', 64);
            $table->string('barcode', 64)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('variant_attributes')->nullable();
            // Money in minor units of the company currency; tax in basis points (1900 = 19%).
            $table->bigInteger('cost')->default(0);
            $table->bigInteger('price')->default(0);
            $table->unsignedInteger('tax_rate')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->unique(['company_id', 'barcode']);
            $table->index(['company_id', 'status', 'type']);
            $table->index(['company_id', 'name']);
            TenantBlueprint::foreign($table, 'parent_id', 'products');
            TenantBlueprint::foreign($table, 'category_id', 'categories');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            TenantBlueprint::company($table);
            $table->ulid('product_id');
            $table->string('disk', 20);
            $table->string('path');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'position']);
            TenantBlueprint::foreign($table, 'product_id', 'products', onDelete: 'cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('warehouses');
    }
};
