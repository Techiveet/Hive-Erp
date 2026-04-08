<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscription_orders', function (Blueprint $table) {
            $table->string('payment_channel')->default('gateway')->after('provider');
            $table->string('manual_payment_bank_account_id')->nullable()->index()->after('provider_checkout_url');
            $table->json('manual_payment_bank_account_snapshot')->nullable()->after('manual_payment_bank_account_id');
            $table->string('manual_payment_reference')->nullable()->index()->after('manual_payment_bank_account_snapshot');
            $table->timestamp('manual_payment_submitted_at')->nullable()->after('manual_payment_reference');
            $table->string('manual_review_status')->nullable()->index()->after('manual_payment_submitted_at');
            $table->text('manual_review_notes')->nullable()->after('manual_review_status');
            $table->string('manual_reviewed_by')->nullable()->after('manual_review_notes');
            $table->timestamp('manual_reviewed_at')->nullable()->after('manual_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscription_orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_channel',
                'manual_payment_bank_account_id',
                'manual_payment_bank_account_snapshot',
                'manual_payment_reference',
                'manual_payment_submitted_at',
                'manual_review_status',
                'manual_review_notes',
                'manual_reviewed_by',
                'manual_reviewed_at',
            ]);
        });
    }
};
