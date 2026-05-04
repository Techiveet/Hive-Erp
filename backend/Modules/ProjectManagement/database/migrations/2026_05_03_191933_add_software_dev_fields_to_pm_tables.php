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
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->string('repository_url')->nullable()->after('description');
            $table->json('tech_stack')->nullable()->after('repository_url');
        });

        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->string('issue_type')->default('task')->after('title'); // task, bug, feature, refactor, debt
            $table->integer('story_points')->nullable()->after('issue_type');
            $table->string('environment')->nullable()->after('story_points'); // local, dev, staging, prod
            $table->string('pr_url')->nullable()->after('environment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->dropColumn(['repository_url', 'tech_stack']);
        });

        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->dropColumn(['issue_type', 'story_points', 'environment', 'pr_url']);
        });
    }
};
