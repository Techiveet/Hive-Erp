<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 120)->default('central')->index();
            $table->string('name', 255);
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'parent_id']);
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 120)->default('central')->index();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 120)->default('central')->index();
            $table->string('name', 255);
            $table->string('code', 120)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 120)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 120)->default('central')->index();
            $table->string('name', 255);
            $table->string('sku', 255);
            $table->string('stock_code', 255)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('parent_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('unit', 50)->nullable();
            $table->string('uom', 50)->nullable();
            $table->unsignedInteger('units_per_package')->nullable();
            $table->integer('reorder_point')->default(0);
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(15);
            $table->decimal('cost_of_good', 14, 2)->default(0);
            $table->decimal('sale_price', 14, 2)->nullable();
            $table->string('barcode', 255)->nullable();
            $table->string('barcode_path', 1024)->nullable();
            $table->string('image', 1024)->nullable();
            $table->string('model_3d_path', 1024)->nullable();
            $table->string('hs_code', 255)->nullable();
            $table->char('country_of_origin', 2)->nullable();
            $table->json('nutritional_info')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->boolean('allow_backorders')->default(false);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->decimal('weight', 14, 3)->nullable();
            $table->decimal('length', 14, 3)->nullable();
            $table->decimal('width', 14, 3)->nullable();
            $table->decimal('height', 14, 3)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'product_category_id']);
            $table->index(['tenant_id', 'supplier_id']);
            $table->index(['tenant_id', 'parent_product_id']);
        });

        Schema::create('product_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('product_categories');
    }
};
