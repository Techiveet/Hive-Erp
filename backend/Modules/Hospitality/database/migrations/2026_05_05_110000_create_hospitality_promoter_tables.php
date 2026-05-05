<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hospitality_guest_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promoter_id')->nullable(); // Links to users
            $table->string('guest_name');
            $table->integer('expected_party_size')->default(1);
            $table->integer('actual_arrived_count')->default(0);
            $table->enum('status', ['pending', 'arrived', 'no-show'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('promoter_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('hospitality_promoter_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promoter_id');
            $table->date('date');
            $table->integer('total_guests_brought')->default(0);
            $table->decimal('commission_earned', 15, 2)->default(0.00);
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('promoter_id')->references('id')->on('users')->onDelete('cascade');
            
            // Ensure one commission entry per promoter per day
            $table->unique(['promoter_id', 'date'], 'promoter_commission_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hospitality_promoter_commissions');
        Schema::dropIfExists('hospitality_guest_lists');
    }
};
