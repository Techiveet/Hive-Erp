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
            $table->decimal('budget', 15, 2)->nullable()->after('priority');
            $table->string('currency', 3)->default('USD')->after('budget');
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('currency');
            $table->decimal('estimated_hours', 10, 2)->nullable()->after('hourly_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->dropColumn(['budget', 'currency', 'hourly_rate', 'estimated_hours']);
        });
    }
};
