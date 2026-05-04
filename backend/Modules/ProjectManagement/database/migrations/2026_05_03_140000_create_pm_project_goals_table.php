<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pm_project_goals')) {
            Schema::create('pm_project_goals', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('project_id')->constrained('pm_projects')->onDelete('cascade');
                $table->string('title');
                $table->boolean('is_completed')->default(false);
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_project_goals');
    }
};
