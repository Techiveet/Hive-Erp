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
        // Ensure hospitality_locations has grid_position
        Schema::table('hospitality_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('hospitality_locations', 'grid_position')) {
                $table->json('grid_position')->nullable()->after('label');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hospitality_locations', function (Blueprint $table) {
            $table->dropColumn('grid_position');
        });
    }
};
