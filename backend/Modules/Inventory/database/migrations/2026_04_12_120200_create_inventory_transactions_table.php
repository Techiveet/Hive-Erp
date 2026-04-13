<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->string('type')->default('manual_adjustment');
            $table->decimal('quantity', 12, 3);
            $table->decimal('balance_after', 12, 3);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->string('module_source')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('performed_by_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'created_at']);
            $table->index(['module_source', 'reference_type', 'reference_id']);
            $table->index(['direction', 'type']);
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table
                ->foreign('performed_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropForeign(['performed_by_id']);
        });

        Schema::dropIfExists('inventory_transactions');
    }
};
