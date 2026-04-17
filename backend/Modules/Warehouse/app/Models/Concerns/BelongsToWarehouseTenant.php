<?php

namespace Modules\Warehouse\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Modules\Warehouse\Support\WarehouseTenantContext;

trait BelongsToWarehouseTenant
{
    protected static function bootBelongsToWarehouseTenant(): void
    {
        static::creating(function ($model): void {
            if (empty($model->tenant_id)) {
                $model->tenant_id = WarehouseTenantContext::id();
            }
        });

        static::addGlobalScope('warehouse_tenant', function (Builder $builder): void {
            $builder->where($builder->qualifyColumn('tenant_id'), WarehouseTenantContext::id());
        });
    }

    public function scopeForCurrentTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('warehouse_tenant')
            ->where($query->qualifyColumn('tenant_id'), WarehouseTenantContext::id());
    }
}
