<?php

namespace Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionSubmodule extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'subscription_module_id',
        'slug',
        'name',
        'description',
        'route_prefixes',
        'permissions',
        'metadata',
    ];

    protected $casts = [
        'route_prefixes' => 'array',
        'permissions' => 'array',
        'metadata' => 'array',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(SubscriptionModule::class, 'subscription_module_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(SubscriptionFeature::class);
    }
}
