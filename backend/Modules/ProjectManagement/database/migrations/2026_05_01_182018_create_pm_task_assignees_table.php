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
        if (!Schema::hasTable('pm_task_assignees')) {
            Schema::create('pm_task_assignees', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('task_id')->constrained('pm_tasks')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        // Migrate existing data
        if (Schema::hasColumn('pm_tasks', 'assigned_to')) {
            $tasks = DB::table('pm_tasks')->whereNotNull('assigned_to')->get();
            foreach ($tasks as $task) {
                DB::table('pm_task_assignees')->insert([
                    'task_id' => $task->id,
                    'user_id' => $task->assigned_to,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_task_assignees');
    }
};
