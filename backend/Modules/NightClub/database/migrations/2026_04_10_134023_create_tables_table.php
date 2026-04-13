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
        Schema::create('nightclub_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('capacity')->default(4);
            $table->decimal('min_spend', 10, 2)->default(0.00);
            $table->enum('status', ['available', 'reserved', 'occupied'])->default('available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nightclub_tables');
    }
};
