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
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_project_members');
        Schema::dropIfExists('pm_projects');
    }
};
