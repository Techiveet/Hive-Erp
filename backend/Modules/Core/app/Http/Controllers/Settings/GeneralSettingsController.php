<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Core\Models\Setting;

class GeneralSettingsController extends Controller
{
    /**
     * Fetch the current general settings
     */
    public function index(): JsonResponse
    {
        $isTenantContext = $this->isTenantContext();
        $keys = [
            'support_email',
            'support_phone',
            'system_email_name',
            'system_email_address',
            'default_timezone',
            'default_currency',
            'date_format',
            'time_format',
            'max_upload_size',
            'max_upload_unit',
            'session_timeout_minutes',
            'maintenance_mode',
            'maintenance_message',  // 🚀 NEW: Added the live status ticker message
            'enable_registration',
            'require_2fa'
        ];

        // Fetch existing settings from the database
        $settings = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        // Safe defaults so the React frontend form doesn't break on first load
        $defaults = [
            'support_email' => '',
            'support_phone' => '',
            'system_email_name' => 'HIVE.OS',
            'system_email_address' => 'noreply@hive-os.com',
            'default_timezone' => 'UTC',
            'default_currency' => 'USD',
            'date_format' => 'YYYY-MM-DD',
            'time_format' => '24h',
            'max_upload_size' => 10,
            'max_upload_unit' => 'MB',
            'session_timeout_minutes' => 120,
            'maintenance_mode' => false,
            'maintenance_message' => 'HIVE.OS: System neural links are currently undergoing optimization.', // 🚀 NEW
            'enable_registration' => false,
            'require_2fa' => false,
        ];

        // Merge DB data over defaults
        $response = array_merge($defaults, $settings);

        // Explicitly cast numeric/boolean values for React
        $response['max_upload_size'] = (int) $response['max_upload_size'];
        $response['session_timeout_minutes'] = (int) $response['session_timeout_minutes'];
        $response['maintenance_mode'] = filter_var($response['maintenance_mode'], FILTER_VALIDATE_BOOLEAN);
        $response['enable_registration'] = filter_var($response['enable_registration'], FILTER_VALIDATE_BOOLEAN);
        $response['require_2fa'] = filter_var($response['require_2fa'], FILTER_VALIDATE_BOOLEAN);

        // Maintenance mode is a central-node control. Tenant dashboards should
        // never expose or honor a tenant-local maintenance toggle.
        if ($isTenantContext) {
            $response['maintenance_mode'] = false;
            $response['maintenance_message'] = $defaults['maintenance_message'];
        }

        return response()->json([
            'data' => $response
        ]);
    }

    /**
     * Update the general settings
     */
    public function store(Request $request): JsonResponse
    {
        $isTenantContext = $this->isTenantContext();

        // 1. Strict Validation
        $rules = [
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'system_email_name' => 'required|string|max:255',
            'system_email_address' => 'required|email|max:255',
            'default_timezone' => 'required|string|max:100',
            'default_currency' => 'required|string|size:3',
            'date_format' => 'required|string|max:20',
            'time_format' => 'required|in:12h,24h',
            'max_upload_size' => 'required|integer|min:1',
            'max_upload_unit' => 'required|in:KB,MB,GB,TB',
            'session_timeout_minutes' => 'required|integer|min:1|max:1440',
            'enable_registration' => 'required|boolean',
            'require_2fa' => 'required|boolean',
        ];

        if (!$isTenantContext) {
            $rules['maintenance_mode'] = 'required|boolean';
            $rules['maintenance_message'] = 'nullable|string|max:255';
        }

        $validated = $request->validate($rules);
        $settingsToPersist = $validated;

        if ($isTenantContext) {
            unset($settingsToPersist['maintenance_mode'], $settingsToPersist['maintenance_message']);
            $validated['maintenance_mode'] = false;
            $validated['maintenance_message'] = 'HIVE.OS: System neural links are currently undergoing optimization.';
        }

        // 2. Save/Update each key in the database
        foreach ($settingsToPersist as $key => $value) {
            // Convert booleans to strings for DB storage
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value ?? ''] // Prevent nulls from breaking the DB column
            );
        }

        // 🚀 3. CRITICAL: Clear the settings cache!
        clear_system_settings_cache();

        return response()->json([
            'message' => 'System configuration updated successfully.',
            'data' => $validated
        ]);
    }

    private function isTenantContext(): bool
    {
        return function_exists('tenant') && (bool) tenant('id');
    }
}
