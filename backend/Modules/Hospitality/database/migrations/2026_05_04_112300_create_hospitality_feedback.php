<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitality_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->nullable()->constrained('hospitality_reservations')->nullOnDelete();
            $table->foreignId('service_order_id')->nullable()->constrained('hospitality_service_orders')->nullOnDelete();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 60)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->unsignedTinyInteger('food_rating')->nullable();
            $table->unsignedTinyInteger('service_rating')->nullable();
            $table->unsignedTinyInteger('ambiance_rating')->nullable();
            $table->text('comment')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_published')->default(false);
            $table->dateTime('responded_at')->nullable();
            $table->text('response')->nullable();
            $table->foreignId('responded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_feedback');
    }
};
