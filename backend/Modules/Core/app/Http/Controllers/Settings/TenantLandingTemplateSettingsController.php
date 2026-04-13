<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tenancy\Support\TenantLandingTemplateCatalog;

class TenantLandingTemplateSettingsController extends Controller
{
    public function __construct(
        protected TenantLandingTemplateCatalog $landingTemplates,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'business_types' => $this->landingTemplates->businessTypesPayload(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_types' => 'required|array|min:1',
            'business_types.*.key' => 'required|string|max:80',
            'business_types.*.label' => 'nullable|string|max:120',
            'business_types.*.description' => 'nullable|string|max:255',
            'business_types.*.icon' => 'nullable|string|max:80',
            'business_types.*.default_template_key' => 'nullable|string|max:120',
            'business_types.*.templates' => 'nullable|array',
        ]);

        $businessTypes = $this->landingTemplates->persistBusinessTypesPayload(
            $validated['business_types']
        );

        return response()->json([
            'message' => 'Landing templates updated successfully.',
            'data' => [
                'business_types' => $businessTypes,
            ],
        ]);
    }
}
