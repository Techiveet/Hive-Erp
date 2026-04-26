<?php

namespace Modules\Inventory\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Modules\Inventory\Support\InventoryTenantContext;

trait BelongsToInventoryTenant
{
    public function getConnectionName()
    {
        return 'central';
    }

    protected static function bootBelongsToInventoryTenant(): void
    {
        static::creating(function ($model): void {
            if (empty($model->tenant_id)) {
                $model->tenant_id = InventoryTenantContext::id();
            }
        });

        static::updating(function ($model): void {
            $model->tenant_id = $model->tenant_id ?? InventoryTenantContext::id();
        });

        static::addGlobalScope('inventory_tenant', function (Builder $builder): void {
            $builder->where($builder->qualifyColumn('tenant_id'), InventoryTenantContext::id());
        });
    }

    public function scopeForCurrentTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('inventory_tenant')
            ->where($query->qualifyColumn('tenant_id'), InventoryTenantContext::id());
    }
}

