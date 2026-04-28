<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_entity_records', function (Blueprint $table): void {
            $table->string('tenant_id')->default('central')->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_entity_records', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });
    }
};
