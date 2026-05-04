<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitality_service_order_items', function (Blueprint $table): void {
            $table->json('inventory_item_snapshot')->nullable()->after('inventory_transaction_id');
            $table->json('inventory_transaction_snapshot')->nullable()->after('inventory_item_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('hospitality_service_order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'inventory_item_snapshot',
                'inventory_transaction_snapshot',
            ]);
        });
    }
};
