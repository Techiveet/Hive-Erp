<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitality_service_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_order_id')->constrained('hospitality_service_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->unsignedBigInteger('inventory_transaction_id')->nullable();
            $table->string('item_name');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->boolean('stock_deducted')->default(false);
            $table->timestamps();

            $table->index(['service_order_id', 'stock_deducted']);
            $table->index('inventory_item_id');
            $table->index('inventory_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_service_order_items');
    }
};
