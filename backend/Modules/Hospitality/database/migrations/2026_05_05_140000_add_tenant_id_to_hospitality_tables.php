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
        $tables = [
            'hospitality_zones',
            'hospitality_locations',
            'hospitality_zone_assignments',
            'hospitality_guest_lists',
            'hospitality_promoter_commissions'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'tenant_id')) {
                        $table->string('tenant_id')->nullable()->after('id');
                        $table->index('tenant_id');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'hospitality_zones',
            'hospitality_locations',
            'hospitality_zone_assignments',
            'hospitality_guest_lists',
            'hospitality_promoter_commissions'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'tenant_id')) {
                        $table->dropColumn('tenant_id');
                    }
                });
            }
        }
    }
};
