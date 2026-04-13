<?php

namespace Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSubscriptionOrder extends Model
{
    protected $connection = 'central';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'public_token',
        'scope',
        'status',
        'provider',
        'payment_channel',
        'currency',
        'tenant_id',
        'subscription_id',
        'tenant_name',
        'tenant_domain',
        'plan',
        'business_type',
        'admin_name',
        'admin_email',
        'admin_password_encrypted',
        'billing_phone',
        'module_request',
        'custom_modules',
        'line_items',
        'provider_payload',
        'provider_status_payload',
        'notify_payload',
        'plan_amount_etb',
        'addon_amount_etb',
        'total_amount_etb',
        'provider_session_id',
        'provider_transaction_id',
        'provider_checkout_url',
        'manual_payment_bank_account_id',
        'manual_payment_bank_account_snapshot',
        'manual_payment_reference',
        'manual_payment_submitted_at',
        'manual_review_status',
        'manual_review_notes',
        'manual_reviewed_by',
        'manual_reviewed_at',
        'renewal_term_days',
        'paid_at',
        'provisioned_at',
    ];

    protected $casts = [
        'module_request' => 'array',
        'custom_modules' => 'array',
        'line_items' => 'array',
        'provider_payload' => 'array',
        'provider_status_payload' => 'array',
        'notify_payload' => 'array',
        'plan_amount_etb' => 'decimal:2',
        'addon_amount_etb' => 'decimal:2',
        'total_amount_etb' => 'decimal:2',
        'manual_payment_bank_account_snapshot' => 'array',
        'manual_payment_submitted_at' => 'datetime',
        'manual_reviewed_at' => 'datetime',
        'renewal_term_days' => 'integer',
        'paid_at' => 'datetime',
        'provisioned_at' => 'datetime',
    ];

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'provisioned'], true);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending_payment', 'payment_processing', 'pending_manual_review'], true);
    }
}

