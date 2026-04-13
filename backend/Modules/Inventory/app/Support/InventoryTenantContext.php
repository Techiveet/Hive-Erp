<?php

namespace Modules\Inventory\Support;

class InventoryTenantContext
{
    public static function id(): string
    {
        if (!function_exists('tenant')) {
            return 'central';
        }

        try {
            $tenantId = tenant('id');
            if (!empty($tenantId)) {
                return (string) $tenantId;
            }

            $tenant = tenant();
            if ($tenant && !empty($tenant->id)) {
                return (string) $tenant->id;
            }
        } catch (\Throwable) {
            // Ignore and use central fallback.
        }

        return 'central';
    }
}

