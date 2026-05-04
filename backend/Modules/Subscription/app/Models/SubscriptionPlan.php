<?php

namespace Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Subscription\Database\Factories\SubscriptionPlanFactory;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'status',
        'billing_cycle',
        'monthly_price_etb',
        'mail_storage_quota_mb',
        'trial_days',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'monthly_price_etb' => 'float',
        'mail_storage_quota_mb' => 'integer',
        'trial_days' => 'integer',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionFeature::class, 'subscription_plan_features')
            ->withPivot(['included', 'limits'])
            ->withTimestamps();
    }

    protected static function newFactory(): SubscriptionPlanFactory
    {
        return SubscriptionPlanFactory::new();
    }
}
