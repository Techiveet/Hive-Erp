<?php

namespace Modules\ProjectManagement\Support;

class ProjectManagementTenantContext
{
    protected static ?string $tenantId = null;

    public static function setTenantId(?string $tenantId): void
    {
        static::$tenantId = $tenantId;
    }

    public static function getTenantId(): ?string
    {
        if (static::$tenantId) {
            return static::$tenantId;
        }

        // Fallback to global tenant helper if available
        if (function_exists('tenant')) {
            return tenant('id');
        }

        return null;
    }
}
