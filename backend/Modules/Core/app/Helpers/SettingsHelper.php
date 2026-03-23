<?php

use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Setting;

if (!function_exists('get_system_setting')) {
    /**
     * Retrieve a setting by key, cached forever until updated.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function get_system_setting($key, $default = null) {
        // Cache the settings array to prevent database hits on every request
        $settings = Cache::rememberForever('global_system_settings', function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        if (!array_key_exists($key, $settings)) {
            return $default;
        }

        $value = $settings[$key];

        // Cast boolean strings back to actual booleans
        if ($value === 'true') return true;
        if ($value === 'false') return false;

        return $value;
    }
}
