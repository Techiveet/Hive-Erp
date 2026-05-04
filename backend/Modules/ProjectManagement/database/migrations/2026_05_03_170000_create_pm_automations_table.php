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
        Schema::create('pm_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('project_id')->constrained('pm_projects')->onDelete('cascade');
            $table->string('name');
            $table->string('trigger'); // e.g., task_status_changed
            $table->json('conditions')->nullable(); // e.g., { "status": "done" }
            $table->string('action'); // e.g., send_notification, create_subtask
            $table->json('action_data')->nullable(); // e.g., { "to": "manager", "message": "Task completed!" }
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_automations');
    }
};
