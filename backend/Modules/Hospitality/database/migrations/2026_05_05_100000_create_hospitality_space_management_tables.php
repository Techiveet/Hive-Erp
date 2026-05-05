<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create Zones table
        Schema::create('hospitality_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Rename hospitality_tables to hospitality_locations
        Schema::rename('hospitality_tables', 'hospitality_locations');

        Schema::table('hospitality_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('hospitality_locations', 'zone_id')) {
                $table->foreignId('zone_id')->nullable()->after('id')->constrained('hospitality_zones')->nullOnDelete();
            }

            if (Schema::hasColumn('hospitality_locations', 'name')) {
                $table->renameColumn('name', 'label');
            }

            // capacity and min_spend already exist based on our check, so we don't need to add them.
            // But we might want to ensure they have the right types/defaults if they were different.
        });

        // 4. Update foreign keys in other tables
        Schema::table('hospitality_reservations', function (Blueprint $table) {
            $table->renameColumn('table_id', 'location_id');
        });

        Schema::table('hospitality_service_orders', function (Blueprint $table) {
            $table->renameColumn('table_id', 'location_id');
        });

        Schema::rename('hospitality_event_tables', 'hospitality_event_locations');
        Schema::table('hospitality_event_locations', function (Blueprint $table) {
            $table->renameColumn('table_id', 'location_id');
        });

        // 5. Create Zone Assignments table
        Schema::create('hospitality_zone_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id'); // Previously user_id
            $table->foreignId('zone_id')->constrained('hospitality_zones')->cascadeOnDelete();
            $table->date('shift_date')->nullable();
            $table->timestamps();
            
            $table->foreign('employee_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->unique(['employee_id', 'zone_id', 'shift_date'], 'zone_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_zone_assignments');
        
        Schema::table('hospitality_event_locations', function (Blueprint $table) {
            $table->renameColumn('location_id', 'table_id');
        });
        Schema::rename('hospitality_event_locations', 'hospitality_event_tables');

        Schema::table('hospitality_service_orders', function (Blueprint $table) {
            $table->renameColumn('location_id', 'table_id');
        });

        Schema::table('hospitality_reservations', function (Blueprint $table) {
            $table->renameColumn('location_id', 'table_id');
        });

        Schema::table('hospitality_locations', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
            $table->dropColumn(['zone_id']);
        });

        Schema::rename('hospitality_locations', 'hospitality_tables');
        Schema::dropIfExists('hospitality_zones');
    }
};
