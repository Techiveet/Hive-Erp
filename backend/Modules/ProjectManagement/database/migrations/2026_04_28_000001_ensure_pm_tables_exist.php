<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_projects')) {
            Schema::create('pm_projects', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('planning');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('pm_project_members')) {
            Schema::create('pm_project_members', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('project_id')->constrained('pm_projects')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users');
                $table->string('role')->default('member');
                $table->timestamps();

                $table->unique(['project_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('pm_boards')) {
            Schema::create('pm_boards', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('project_id')->constrained('pm_projects')->onDelete('cascade');
                $table->string('name');
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_columns')) {
            Schema::create('pm_columns', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('board_id')->constrained('pm_boards')->onDelete('cascade');
                $table->string('name');
                $table->string('color')->nullable();
                $table->integer('order')->default(0);
                $table->boolean('is_done')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_tasks')) {
            Schema::create('pm_tasks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('project_id')->constrained('pm_projects')->onDelete('cascade');
                $table->foreignUuid('column_id')->constrained('pm_columns')->onDelete('cascade');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('priority')->default('medium');
                $table->date('due_date')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users');
                $table->foreignId('created_by')->constrained('users');
                $table->integer('order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

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
        //
    }
};
