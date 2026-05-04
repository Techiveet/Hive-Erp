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
        Schema::table('pm_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_tasks', 'tags')) {
                $table->json('tags')->nullable()->after('description');
            }
            if (!Schema::hasColumn('pm_tasks', 'progress')) {
                $table->integer('progress')->default(0)->after('priority');
            }
            if (!Schema::hasColumn('pm_tasks', 'effort')) {
                $table->string('effort')->nullable()->after('progress');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->dropColumn(['tags', 'progress', 'effort']);
        });
    }
};
