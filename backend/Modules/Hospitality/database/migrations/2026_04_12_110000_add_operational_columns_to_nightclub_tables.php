<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitality_tables', function (Blueprint $table): void {
            if (!Schema::hasColumn('hospitality_tables', 'zone')) {
                $table->string('zone')->default('main')->after('name');
            }

            if (!Schema::hasColumn('hospitality_tables', 'table_type')) {
                $table->string('table_type')->default('standard')->after('zone');
            }

            if (!Schema::hasColumn('hospitality_tables', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('assigned_staff_id');
            }

            if (!Schema::hasColumn('hospitality_tables', 'notes')) {
                $table->text('notes')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hospitality_tables', function (Blueprint $table): void {
            $columns = collect(['zone', 'table_type', 'is_active', 'notes'])
                ->filter(fn (string $column): bool => Schema::hasColumn('hospitality_tables', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
