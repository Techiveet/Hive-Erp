<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitality_events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('event_type', 40)->default('party');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('is_private')->default(false);
            $table->unsignedInteger('min_guests')->nullable();
            $table->unsignedInteger('max_guests')->nullable();
            $table->decimal('ticket_price', 14, 2)->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cover_image_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('hospitality_event_tables', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained('hospitality_events')->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('hospitality_tables')->cascadeOnDelete();
            $table->primary(['event_id', 'table_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_event_tables');
        Schema::dropIfExists('hospitality_events');
    }
};
