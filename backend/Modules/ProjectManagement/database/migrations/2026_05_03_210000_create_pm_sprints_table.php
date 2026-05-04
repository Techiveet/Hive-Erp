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
        Schema::create('pm_sprints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('name');
            $table->text('goal')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('upcoming'); // upcoming, active, completed
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('project_id')->references('id')->on('pm_projects')->onDelete('cascade');
        });

        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->boolean('is_backlog')->default(true)->after('order');
            $table->uuid('sprint_id')->nullable()->after('is_backlog');
            $table->foreign('sprint_id')->references('id')->on('pm_sprints')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->dropForeign(['sprint_id']);
            $table->dropColumn(['is_backlog', 'sprint_id']);
        });

        Schema::dropIfExists('pm_sprints');
    }
};
