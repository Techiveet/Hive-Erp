<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitality_menu_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('color', 7)->nullable();
            $table->string('icon', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('hospitality_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('hospitality_menu_categories')->cascadeOnDelete();
            $table->unsignedBigInteger('inventory_item_id')->nullable()->index();
            $table->string('name', 120);
            $table->string('slug', 120)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('cost_price', 14, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('preparation_time_minutes')->nullable();
            $table->json('allergens')->nullable();
            $table->json('tags')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_menu_items');
        Schema::dropIfExists('hospitality_menu_categories');
    }
};
