<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Setting;

class BrandSettingsController extends Controller
{
    // Define all valid brand keys for the dashboard
    private $allBrandKeys = [
        'logo_light', 'logo_dark', 'favicon', 'sidebar_icon', 'app_title', 'footer_text',
        'primary_color', 'auth_background_image', 'auth_welcome_message', 'font_family',
        'meta_description', 'og_image', 'hide_watermark', 'document_header_color',
        'company_tax_id', 'pdf_logo'
    ];

    // Define keys safe to expose to unauthenticated visitors on the landing/login pages
    private $publicBrandKeys = [
        'logo_light', 'logo_dark', 'favicon', 'sidebar_icon', 'app_title', 'footer_text',
        'primary_color', 'auth_background_image', 'auth_welcome_message', 'font_family',
        'meta_description', 'og_image', 'hide_watermark'
    ];

    /**
     * Fetch ALL brand settings (Protected - used inside the dashboard)
     */
    public function getBrandSettings()
    {
        return response()->json([
            'success' => true,
            'data' => $this->resolveBrandSettings($this->allBrandKeys, 'protected')
        ]);
    }

    /**
     * Fetch PUBLIC brand settings (Unprotected - used on landing & auth pages)
     */
    public function getPublicBrandSettings()
    {
        return response()->json([
            'success' => true,
            'data' => $this->resolveBrandSettings($this->publicBrandKeys, 'public')
        ]);
    }

    /**
     * Update the brand settings
     */
    public function updateBrandSettings(Request $request)
    {
        $validated = $request->validate([
            'logo_light'            => 'nullable|string',
            'logo_dark'             => 'nullable|string',
            'favicon'               => 'nullable|string',
            'sidebar_icon'          => 'nullable|string',
            'app_title'             => 'nullable|string|max:255',
            'footer_text'           => 'nullable|string|max:255',
            'primary_color'         => 'nullable|string|max:20',
            'auth_background_image' => 'nullable|string',
            'auth_welcome_message'  => 'nullable|string|max:500',
            'font_family'           => 'nullable|string|max:50',
            'meta_description'      => 'nullable|string|max:500',
            'og_image'              => 'nullable|string',
            'hide_watermark'        => 'nullable|boolean',
            'document_header_color' => 'nullable|string|max:20',
            'company_tax_id'        => 'nullable|string|max:50',
            'pdf_logo'              => 'nullable|string',
        ]);

        // Update or create each setting
        foreach ($validated as $key => $value) {
            // Databases handle strings better for generic key-value tables
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $this->clearBrandSettingsCache();

        return response()->json([
            'success' => true,
            'message' => 'White-Label Settings matrix updated successfully.'
        ]);
    }

    private function resolveBrandSettings(array $keys, string $scope): array
    {
        return Cache::rememberForever($this->brandSettingsCacheKey($scope), function () use ($keys) {
            $settings = Setting::whereIn('key', $keys)->pluck('value', 'key');

            $data = [];
            foreach ($keys as $key) {
                $data[$key] = $settings[$key] ?? null;
            }

            $data['app_title'] = $data['app_title'] ?? 'HIVE.OS';
            $data['footer_text'] = $data['footer_text'] ?? 'Powered by HIVE.OS';
            $data['primary_color'] = $data['primary_color'] ?? '#10b981';
            $data['document_header_color'] = $data['document_header_color'] ?? '#1e293b';
            $data['font_family'] = $data['font_family'] ?? 'Inter';
            $data['auth_welcome_message'] = $data['auth_welcome_message'] ?? 'Sign in to access your secure control hub.';
            $data['hide_watermark'] = filter_var($data['hide_watermark'] ?? false, FILTER_VALIDATE_BOOLEAN);

            return $data;
        });
    }

    private function clearBrandSettingsCache(): void
    {
        Cache::forget($this->brandSettingsCacheKey('protected'));
        Cache::forget($this->brandSettingsCacheKey('public'));
    }

    private function brandSettingsCacheKey(string $scope): string
    {
        $context = 'central';

        if (function_exists('tenant') && tenant('id')) {
            $context = 'tenant:' . tenant('id');
        }

        return "brand_settings:{$scope}:{$context}";
    }
}
