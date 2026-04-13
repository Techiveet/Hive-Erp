<?php

namespace Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantDomainService;
use Modules\Tenancy\Support\TenantLandingTemplateCatalog;

class TenantLandingController extends Controller
{
    public function __construct(
        protected TenantDomainService $tenantDomains,
        protected TenantLandingTemplateCatalog $landingTemplates,
    ) {
    }

    public function showPublic()
    {
        /** @var Tenant|null $tenant */
        $tenant = function_exists('tenant') ? tenant() : null;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant context was not initialized for this request.'], 404);
        }

        $tenant->loadMissing('domains');
        $businessType = $this->landingTemplates->normalizeBusinessType($tenant->business_type ?? null);
        $primaryDomain = $tenant->primaryDomain();
        $fallbackDomain = $tenant->fallbackDomain();
        $expectedFallbackDomain = $this->tenantDomains->expectedFallbackDomain($tenant);

        return response()->json([
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name ?? ucfirst($tenant->id),
                    'domain' => $primaryDomain?->domain ?? $expectedFallbackDomain,
                    'primary_domain' => $primaryDomain?->domain ?? $expectedFallbackDomain,
                    'fallback_domain' => $fallbackDomain?->domain ?? $expectedFallbackDomain,
                ],
                'business_type' => $businessType,
                'business_type_meta' => $this->landingTemplates->businessTypeMeta($businessType),
                'landing_page_template' => $this->landingTemplates->normalizeTemplate(
                    $tenant->landing_page_template ?? null,
                    $businessType
                ),
            ],
        ], 200);
    }
}
