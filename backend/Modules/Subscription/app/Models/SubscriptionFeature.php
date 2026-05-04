<?php

namespace Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Subscription\Database\Factories\SubscriptionFeatureFactory;

class SubscriptionFeature extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'subscription_module_id',
        'subscription_submodule_id',
        'slug',
        'name',
        'feature_type',
        'route_name',
        'route_uri',
        'http_methods',
        'permission',
        'module_gate',
        'metadata',
    ];

    protected $casts = [
        'http_methods' => 'array',
        'metadata' => 'array',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(SubscriptionModule::class, 'subscription_module_id');
    }

    public function submodule(): BelongsTo
    {
        return $this->belongsTo(SubscriptionSubmodule::class, 'subscription_submodule_id');
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'subscription_plan_features')
            ->withPivot(['included', 'limits'])
            ->withTimestamps();
    }

    protected static function newFactory(): SubscriptionFeatureFactory
    {
        return SubscriptionFeatureFactory::new();
    }
}
