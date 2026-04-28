<?php

namespace Modules\ProjectManagement\Models\Concerns;

use Modules\ProjectManagement\Support\ProjectManagementTenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToProjectManagementTenant
{
    public static function bootBelongsToProjectManagementTenant(): void
    {
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $model->tenant_id = ProjectManagementTenantContext::getTenantId();
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = ProjectManagementTenantContext::getTenantId();
            if ($tenantId) {
                $builder->where($builder->getQuery()->from . '.tenant_id', $tenantId);
            }
        });
    }
}
