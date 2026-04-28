<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_task_checklists')) {
            Schema::create('pm_task_checklists', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('task_id')->constrained('pm_tasks')->onDelete('cascade');
                $table->string('item');
                $table->boolean('is_completed')->default(false);
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_task_comments')) {
            Schema::create('pm_task_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('task_id')->constrained('pm_tasks')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users');
                $table->text('content');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_task_attachments')) {
            Schema::create('pm_task_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('task_id')->constrained('pm_tasks')->onDelete('cascade');
                $table->foreignId('file_entry_id')->constrained('file_entries');
                $table->foreignId('user_id')->constrained('users');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_task_time_logs')) {
            Schema::create('pm_task_time_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('task_id')->constrained('pm_tasks')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users');
                $table->dateTime('started_at');
                $table->dateTime('ended_at')->nullable();
                $table->integer('duration_minutes')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_task_time_logs');
        Schema::dropIfExists('pm_task_attachments');
        Schema::dropIfExists('pm_task_comments');
        Schema::dropIfExists('pm_task_checklists');
    }
};
