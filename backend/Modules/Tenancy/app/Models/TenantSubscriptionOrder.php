<?php

namespace Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSubscriptionOrder extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'public_token',
        'scope',
        'status',
        'provider',
        'currency',
        'tenant_id',
        'tenant_name',
        'tenant_domain',
        'plan',
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
        'paid_at' => 'datetime',
        'provisioned_at' => 'datetime',
    ];

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'provisioned'], true);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending_payment', 'payment_processing'], true);
    }
}
