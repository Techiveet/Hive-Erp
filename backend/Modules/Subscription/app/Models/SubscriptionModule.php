<?php

namespace Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Subscription\Database\Factories\SubscriptionModuleFactory;

class SubscriptionModule extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'category',
        'tone',
        'backend_module',
        'frontend_module',
        'route_prefixes',
        'metadata',
    ];

    protected $casts = [
        'route_prefixes' => 'array',
        'metadata' => 'array',
    ];

    public function submodules(): HasMany
    {
        return $this->hasMany(SubscriptionSubmodule::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(SubscriptionFeature::class);
    }

    protected static function newFactory(): SubscriptionModuleFactory
    {
        return SubscriptionModuleFactory::new();
    }
}
