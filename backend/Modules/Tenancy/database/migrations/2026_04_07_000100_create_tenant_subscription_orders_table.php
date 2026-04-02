<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscription_orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('public_token')->unique();
            $table->string('scope')->default('tenant_upgrade');
            $table->string('status')->default('pending_payment');
            $table->string('provider')->default('arifpay');
            $table->string('currency', 8)->default('ETB');
            $table->string('tenant_id')->nullable()->index();
            $table->string('tenant_name')->nullable();
            $table->string('tenant_domain')->nullable();
            $table->string('plan')->default('business');
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable()->index();
            $table->text('admin_password_encrypted')->nullable();
            $table->string('billing_phone')->nullable();
            $table->json('module_request')->nullable();
            $table->json('custom_modules')->nullable();
            $table->json('line_items')->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('provider_status_payload')->nullable();
            $table->json('notify_payload')->nullable();
            $table->decimal('plan_amount_etb', 12, 2)->default(0);
            $table->decimal('addon_amount_etb', 12, 2)->default(0);
            $table->decimal('total_amount_etb', 12, 2)->default(0);
            $table->string('provider_session_id')->nullable()->index();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->text('provider_checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscription_orders');
    }
};
