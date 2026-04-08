<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id')->unique()->index();
            $table->string('plan')->default('business');
            $table->string('status')->default('active')->index();
            $table->string('billing_cycle')->default('monthly');
            $table->string('renewal_mode')->default('manual');
            $table->json('module_subscriptions')->nullable();
            $table->json('metadata')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('renewal_window_starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('last_renewed_at')->nullable();
            $table->timestamp('renewal_reminder_sent_at')->nullable();
            $table->timestamp('grace_reminder_sent_at')->nullable();
            $table->timestamp('expired_notice_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};
