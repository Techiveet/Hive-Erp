<?php

namespace Modules\Core\Jobs\Concerns;

use Modules\Tenancy\Models\Tenant;
use RuntimeException;
use Stancl\Tenancy\Tenancy;

trait InteractsWithTenantContext
{
    protected function initializeTenantContext(Tenancy $tenancy, string $tenantId): void
    {
        if ($tenantId === 'central') {
            $this->endTenantContext($tenancy);

            return;
        }

        $currentTenantId = (function_exists('tenant') && tenant('id'))
            ? (string) tenant('id')
            : null;

        if ($tenancy->initialized && $currentTenantId === $tenantId) {
            return;
        }

        if ($tenancy->initialized) {
            $tenancy->end();
        }

        $tenant = Tenant::query()->find($tenantId);

        if (!$tenant) {
            throw new RuntimeException(sprintf('Unable to initialize queued tenant context [%s].', $tenantId));
        }

        $tenancy->initialize($tenant);
    }

    protected function endTenantContext(Tenancy $tenancy): void
    {
        if ($tenancy->initialized) {
            $tenancy->end();
        }
    }
}
