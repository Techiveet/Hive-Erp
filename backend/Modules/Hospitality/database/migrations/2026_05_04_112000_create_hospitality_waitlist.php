<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitality_waitlist', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 60);
            $table->unsignedInteger('party_size')->default(1);
            $table->string('preferred_zone', 60)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('waiting');
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('seated_at')->nullable();
            $table->unsignedInteger('estimated_wait_minutes')->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained('hospitality_reservations')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_waitlist');
    }
};
