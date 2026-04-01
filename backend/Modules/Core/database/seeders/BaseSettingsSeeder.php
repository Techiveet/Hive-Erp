<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Setting;

abstract class BaseSettingsSeeder extends Seeder
{
    protected function seedSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $this->normalizeSettingValue($value)]
            );
        }
    }

    protected function normalizeSettingValue(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    protected function clearGeneralSettingsCache(): void
    {
        if (function_exists('clear_system_settings_cache')) {
            clear_system_settings_cache();
        }
    }

    protected function clearBrandSettingsCache(): void
    {
        $context = 'central';

        if (function_exists('tenant')) {
            $tenant = tenant();

            if ($tenant && tenant('id')) {
                $context = 'tenant:' . tenant('id');
            }
        }

        Cache::forget("brand_settings:protected:{$context}");
        Cache::forget("brand_settings:public:{$context}");
    }
}
