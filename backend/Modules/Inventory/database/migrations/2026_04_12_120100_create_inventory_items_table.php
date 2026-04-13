<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('unit')->default('unit');
            $table->decimal('current_stock', 12, 3)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['name', 'is_active']);
            $table->index(['current_stock', 'reorder_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
