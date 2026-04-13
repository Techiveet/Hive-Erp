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
        Schema::table('nightclub_tables', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_staff_id')->nullable()->after('status');
            
            // Assuming users table handles staff. 
            // We use cascadeOnSetNull so if a staff is deleted, the table just becomes unassigned.
            $table->foreign('assigned_staff_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nightclub_tables', function (Blueprint $table) {
            $table->dropForeign(['assigned_staff_id']);
            $table->dropColumn('assigned_staff_id');
        });
    }
};
