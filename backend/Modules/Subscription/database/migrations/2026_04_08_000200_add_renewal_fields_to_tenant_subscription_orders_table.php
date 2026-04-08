<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscription_orders', function (Blueprint $table) {
            $table->string('subscription_id')->nullable()->index()->after('tenant_id');
            $table->unsignedInteger('renewal_term_days')->default(30)->after('provider_checkout_url');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscription_orders', function (Blueprint $table) {
            $table->dropColumn(['subscription_id', 'renewal_term_days']);
        });
    }
};
