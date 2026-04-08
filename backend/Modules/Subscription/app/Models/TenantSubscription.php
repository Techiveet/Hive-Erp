<?php

namespace Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSubscription extends Model
{
    protected $connection = 'central';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'plan',
        'status',
        'billing_cycle',
        'renewal_mode',
        'module_subscriptions',
        'metadata',
        'updated_by',
        'started_at',
        'renewal_window_starts_at',
        'expires_at',
        'grace_ends_at',
        'last_renewed_at',
        'renewal_reminder_sent_at',
        'grace_reminder_sent_at',
        'expired_notice_sent_at',
    ];

    protected $casts = [
        'module_subscriptions' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'renewal_window_starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'last_renewed_at' => 'datetime',
        'renewal_reminder_sent_at' => 'datetime',
        'grace_reminder_sent_at' => 'datetime',
        'expired_notice_sent_at' => 'datetime',
    ];
}
