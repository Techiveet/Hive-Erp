<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Models\Setting;

class EmailSettingsController extends Controller
{
    /**
     * Retrieve the email configuration settings.
     */
    public function index()
    {
        $keys = [
            'mail_driver',
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_from_address',
            'mail_from_name'
        ];

        if (function_exists('tenant') && tenant('id')) {
            // Tenant Admin: can only set the per-user quota within their own org
            $keys[] = 'mail_storage_quota_tenant_users';
        } else {
            // Central Super Admin: sets per-plan tenant quotas + central user quota
            $keys[] = 'mail_storage_quota_central_users';
            $keys[] = 'mail_storage_quota_tenant_larva';
            $keys[] = 'mail_storage_quota_tenant_startup';
            $keys[] = 'mail_storage_quota_tenant_business';
            $keys[] = 'mail_storage_quota_tenant_enterprise';
            $keys[] = 'mail_storage_quota_tenant_overlord';
        }

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = get_system_setting($key);
        }

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    /**
     * Store or update email configuration settings.
     */
    public function store(Request $request)
    {
        $rules = [
            'mail_driver' => 'nullable|string|max:50',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|string|max:50',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255'
        ];

        if (function_exists('tenant') && tenant('id')) {
            // Tenant Admin: only their per-user quota
            $rules['mail_storage_quota_tenant_users'] = 'nullable|integer|min:0';
        } else {
            // Central Super Admin: per-plan overrides
            $rules['mail_storage_quota_central_users']     = 'nullable|integer|min:0';
            $rules['mail_storage_quota_tenant_larva']      = 'nullable|integer|min:0';
            $rules['mail_storage_quota_tenant_startup']    = 'nullable|integer|min:0';
            $rules['mail_storage_quota_tenant_business']   = 'nullable|integer|min:0';
            $rules['mail_storage_quota_tenant_enterprise'] = 'nullable|integer|min:0';
            $rules['mail_storage_quota_tenant_overlord']   = 'nullable|integer|min:0';
        }

        $validated = $request->validate($rules);

        foreach ($validated as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => (string)($value ?? '')]
            );
        }
        clear_system_settings_cache();

        $updatedSettings = [];
        foreach (array_keys($validated) as $key) {
            $updatedSettings[$key] = get_system_setting($key);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Email settings updated successfully.',
            'data' => $updatedSettings
        ]);
    }
}
