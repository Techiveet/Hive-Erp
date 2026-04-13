<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscription_orders', function (Blueprint $table) {
            $table->string('business_type')->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscription_orders', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });
    }
};
