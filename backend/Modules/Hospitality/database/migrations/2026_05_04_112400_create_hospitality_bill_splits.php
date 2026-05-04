<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitality_bill_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('hospitality_service_orders')->cascadeOnDelete();
            $table->string('split_name', 60);
            $table->decimal('amount', 14, 2);
            $table->decimal('tip_amount', 14, 2)->default(0);
            $table->string('payment_method', 20)->default('cash');
            $table->string('payment_reference', 120)->nullable();
            $table->boolean('is_paid')->default(false);
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_bill_splits');
    }
};
