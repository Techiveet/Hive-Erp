<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitality_tables', function (Blueprint $table) {
            $table->decimal('layout_x', 8, 2)->nullable()->after('notes');
            $table->decimal('layout_y', 8, 2)->nullable()->after('layout_x');
            $table->decimal('layout_width', 8, 2)->nullable()->after('layout_y');
            $table->decimal('layout_height', 8, 2)->nullable()->after('layout_width');
            $table->decimal('layout_rotation', 6, 2)->default(0)->after('layout_height');
        });

        Schema::table('hospitality_reservations', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('table_id')->constrained('hospitality_events')->nullOnDelete();
            $table->foreignId('customer_profile_id')->nullable()->after('event_id')->constrained('hospitality_customer_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hospitality_reservations', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropForeign(['customer_profile_id']);
            $table->dropColumn(['event_id', 'customer_profile_id']);
        });

        Schema::table('hospitality_tables', function (Blueprint $table) {
            $table->dropColumn(['layout_x', 'layout_y', 'layout_width', 'layout_height', 'layout_rotation']);
        });
    }
};
