<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->foreignId('project_manager_id')->nullable()->after('end_date')->constrained('users');
            $table->string('client_stakeholder')->nullable()->after('project_manager_id');
            $table->string('priority')->default('medium')->after('client_stakeholder');
            $table->json('tags')->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->dropForeign(['project_manager_id']);
            $table->dropColumn(['project_manager_id', 'client_stakeholder', 'priority', 'tags']);
        });
    }
};
