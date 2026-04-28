<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_tasks');
    }
};
