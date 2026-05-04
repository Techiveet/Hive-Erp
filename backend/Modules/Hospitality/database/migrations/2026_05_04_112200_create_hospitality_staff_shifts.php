<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitality_staff_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->date('shift_date');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('zone', 60)->nullable();
            $table->string('role', 40)->default('waiter');
            $table->boolean('is_confirmed')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['shift_date', 'zone']);
            $table->index(['staff_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_staff_shifts');
    }
};
