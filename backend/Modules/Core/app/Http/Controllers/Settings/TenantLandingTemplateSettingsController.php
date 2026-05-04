<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Support\BrandSettingsStore;
use Modules\Tenancy\Support\TenantLandingTemplateCatalog;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantLandingTemplateSettingsController extends Controller
{
    public function __construct(
        protected TenantLandingTemplateCatalog $landingTemplates,
        protected BrandSettingsStore $brandSettingsStore,
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
        $type = $request->get('type', 'csv');

        $exportData = array_map(function ($type, $index) {
            return [
                '#' => $index + 1,
                'key' => $type['key'] ?? '',
                'label' => $type['label'] ?? '',
                'description' => $type['description'] ?? '',
                'icon' => $type['icon'] ?? '',
            ];
        }, $businessTypes, array_keys($businessTypes));

        if (in_array($type, ['copy', 'print'])) {
            $branding = $this->brandSettingsStore->getProtectedSettings();
            return response()->json([
                'data' => $exportData,
                'branding' => [
                    'app_title' => $branding['app_title'] ?? 'HIVE.OS',
                    'footer_text' => $branding['footer_text'] ?? 'HIVE.OS',
                    'document_header_color' => $branding['document_header_color'] ?? '#1E293B',
                    'company_tax_id' => $branding['company_tax_id'] ?? null,
                    'logo_url' => $branding['logo_light'] ?? null,
                ],
                'exported_at' => now()->toIso8601String(),
                'total' => count($exportData),
            ]);
        }

        $headers = ['#', 'Key', 'Label', 'Description', 'Icon'];
        $rows = array_map(fn($row) => array_values($row), $exportData);

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
