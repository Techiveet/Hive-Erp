<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitality_service_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('table_id')->constrained('hospitality_tables')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('hospitality_reservations')->nullOnDelete();
            $table->enum('status', ['pending', 'preparing', 'served', 'closed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('served_by_id')->nullable();
            $table->timestamps();

            $table->index(['table_id', 'status']);
            $table->index(['reservation_id', 'status']);
        });

        Schema::table('hospitality_service_orders', function (Blueprint $table): void {
            $table
                ->foreign('served_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hospitality_service_orders', function (Blueprint $table): void {
            $table->dropForeign(['served_by_id']);
        });

        Schema::dropIfExists('hospitality_service_orders');
    }
};
