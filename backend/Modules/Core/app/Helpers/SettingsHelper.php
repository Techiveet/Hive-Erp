<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Setting;

if (!function_exists('get_system_settings_cache_key')) {
    /**
     * Build a settings cache key that is isolated per central/tenant context.
     */
    function get_system_settings_cache_key(): string {
        $context = 'central';

        if (function_exists('tenant')) {
            $tenant = tenant();

            if ($tenant && tenant('id')) {
                $context = 'tenant:' . tenant('id');
            }
        }

        return 'global_system_settings:' . $context;
    }
}

if (!function_exists('clear_system_settings_cache')) {
    /**
     * Clear the current context settings cache plus the legacy shared key.
     */
    function clear_system_settings_cache(): void {
        Cache::forget(get_system_settings_cache_key());
        Cache::forget('global_system_settings');
    }
}

if (!function_exists('get_central_system_settings_cache_key')) {
    /**
     * Build the central settings cache key regardless of the active tenant context.
     */
    function get_central_system_settings_cache_key(): string {
        return 'global_system_settings:central';
    }
}

if (!function_exists('get_central_system_setting')) {
    /**
     * Retrieve a central setting even while running inside a tenant request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function get_central_system_setting($key, $default = null) {
        try {
            $centralConnection = (string) config('tenancy.database.central_connection', config('database.default'));

            if ($centralConnection === '' || !Schema::connection($centralConnection)->hasTable('settings')) {
                return $default;
            }

            $settings = Cache::rememberForever(get_central_system_settings_cache_key(), function () use ($centralConnection) {
                return DB::connection($centralConnection)
                    ->table((new Setting())->getTable())
                    ->pluck('value', 'key')
                    ->toArray();
            });
        } catch (\Throwable) {
            return $default;
        }

        if (!array_key_exists($key, $settings)) {
            return $default;
        }

        $value = $settings[$key];

        if ($value === 'true') return true;
        if ($value === 'false') return false;

        return $value;
    }
}

if (!function_exists('get_system_setting')) {
    /**
     * Retrieve a setting by key, cached forever until updated.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function get_system_setting($key, $default = null) {
        try {
            if (!Schema::hasTable('settings')) {
                return $default;
            }

            // Cache the settings array to prevent database hits on every request,
            // but keep each tenant and the central node isolated from one another.
            $settings = Cache::rememberForever(get_system_settings_cache_key(), function () {
                return Setting::query()->pluck('value', 'key')->toArray();
            });
        } catch (\Throwable) {
            return $default;
        }

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

if (!function_exists('get_communication_encryption_setting')) {
    /**
     * Communication encryption is governed centrally for both central and tenant workspaces.
     */
    function get_communication_encryption_setting($default = false): bool {
        return (bool) get_central_system_setting('enable_communication_encryption', $default);
    }
}
