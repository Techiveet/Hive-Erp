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
        // 1. Clean up hospitality_locations
        Schema::table('hospitality_locations', function (Blueprint $table) {
            if (Schema::hasColumn('hospitality_locations', 'tenant_id')) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('ALTER TABLE hospitality_locations DROP CONSTRAINT IF EXISTS hospitality_locations_tenant_id_foreign');
                } else {
                    try {
                        $table->dropForeign(['tenant_id']);
                    } catch (\Exception $e) {
                        // Ignore if not exists
                    }
                }
                $table->dropColumn('tenant_id');
            }

            if (!Schema::hasColumn('hospitality_locations', 'grid_position')) {
                $table->json('grid_position')->nullable()->after('label');
            }
            
            // Fix status for Postgres
            if (DB::getDriverName() === 'pgsql') {
                // Legally drop ALL check constraints on the status column by querying the schema
                DB::statement("
                    DO $$ 
                    DECLARE 
                        constr_name TEXT;
                    BEGIN 
                        FOR constr_name IN 
                            SELECT tc.constraint_name 
                            FROM information_schema.table_constraints AS tc 
                            JOIN information_schema.constraint_column_usage AS ccu 
                              ON ccu.constraint_name = tc.constraint_name 
                              AND ccu.table_schema = tc.table_schema
                            WHERE tc.constraint_type = 'CHECK' 
                              AND tc.table_name = 'hospitality_locations' 
                              AND ccu.column_name = 'status'
                        LOOP 
                            EXECUTE 'ALTER TABLE hospitality_locations DROP CONSTRAINT ' || constr_name; 
                        END LOOP; 
                    END $$;
                ");
                
                // Ensure column is varchar with proper casting
                DB::statement('ALTER TABLE hospitality_locations ALTER COLUMN status TYPE varchar(255) USING status::varchar');
                
                // Add new check constraint
                DB::statement("ALTER TABLE hospitality_locations ADD CONSTRAINT hospitality_locations_status_check CHECK (status IN ('available', 'reserved', 'occupied', 'dirty'))");
            } else {
                $table->enum('status', ['available', 'reserved', 'occupied', 'dirty'])->default('available')->change();
            }
        });

        // 2. Clean up other hospitality tables from redundant tenant_id
        $tablesToClean = [
            'hospitality_zones',
            'hospitality_zone_assignments',
            'hospitality_guest_lists',
            'hospitality_promoter_commissions',
            'hospitality_reservations',
            'hospitality_service_orders'
        ];

        foreach ($tablesToClean as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'tenant_id')) {
                        if (DB::getDriverName() === 'pgsql') {
                            DB::statement("ALTER TABLE {$tableName} DROP CONSTRAINT IF EXISTS {$tableName}_tenant_id_foreign");
                        } else {
                            try {
                                $table->dropForeign([ 'tenant_id']);
                            } catch (\Exception $e) {
                                // Ignore if not exists
                            }
                        }
                        $table->dropColumn('tenant_id');
                    }
                });
            }
        }

        // 3. Fix Unique Constraints that might have included tenant_id
        if (Schema::hasTable('hospitality_promoter_commissions')) {
            // Get the current unique constraints to check if we need to drop
            $indexes = Schema::getIndexes('hospitality_promoter_commissions');
            $hasUnique = false;
            foreach ($indexes as $index) {
                if ($index['name'] === 'promoter_commission_unique') {
                    $hasUnique = true;
                    break;
                }
            }
            
            if ($hasUnique) {
                Schema::table('hospitality_promoter_commissions', function (Blueprint $table) {
                    $table->dropUnique('promoter_commission_unique');
                });
            }

            Schema::table('hospitality_promoter_commissions', function (Blueprint $table) {
                // Re-add without tenant_id
                $table->unique(['promoter_id', 'date'], 'promoter_commission_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hospitality_locations', function (Blueprint $table) {
            $table->dropColumn('grid_position');
            // Reverting enum change is tricky in Laravel without a specific DB engine support, 
            // usually we'd just leave it or change back to string if it was.
        });
    }
};
