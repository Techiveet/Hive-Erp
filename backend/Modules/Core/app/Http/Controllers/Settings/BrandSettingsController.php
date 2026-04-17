<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Models\Setting;
use Modules\Core\Support\BrandSettingsStore;

class BrandSettingsController extends Controller
{
    public function __construct(private readonly BrandSettingsStore $brandSettingsStore) {}

    /**
     * Fetch ALL brand settings (Protected - used inside the dashboard)
     */
    public function getBrandSettings()
    {
        return response()->json([
            'success' => true,
            'data' => $this->brandSettingsStore->getProtectedSettings(),
        ]);
    }

    /**
     * Fetch PUBLIC brand settings (Unprotected - used on landing & auth pages)
     */
    public function getPublicBrandSettings()
    {
        return response()->json([
            'success' => true,
            'data' => $this->brandSettingsStore->getPublicSettings(),
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

        $this->brandSettingsStore->clearCache();

        return response()->json([
            'success' => true,
            'message' => 'White-Label Settings matrix updated successfully.'
        ]);
    }
}
