<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tenancy\Support\TenantLandingTemplateCatalog;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $businessTypes = $this->landingTemplates->businessTypesPayload();
        $format = $request->get('format', 'csv');

        if ($format === 'json') {
            return response()->json([
                'data' => $businessTypes,
                'exported_at' => now()->toIso8601String(),
                'total' => count($businessTypes),
            ]);
        }

        $headers = ['#', 'Key', 'Label', 'Description', 'Icon'];
        $rows = array_map(function ($type, $index) {
            return [
                $index + 1,
                $type['key'] ?? '',
                $type['label'] ?? '',
                $type['description'] ?? '',
                $type['icon'] ?? '',
            ];
        }, $businessTypes, array_keys($businessTypes));

        $callback = function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="business-types-' . date('Y-m-d') . '.csv"',
        ]);
    }
}
