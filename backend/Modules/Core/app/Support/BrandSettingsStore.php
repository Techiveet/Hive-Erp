<?php

namespace Modules\Core\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Setting;

class BrandSettingsStore
{
    public const ALL_KEYS = [
        'logo_light', 'logo_dark', 'favicon', 'sidebar_icon', 'app_title', 'footer_text',
        'primary_color', 'auth_background_image', 'auth_welcome_message', 'font_family',
        'meta_description', 'og_image', 'hide_watermark', 'document_header_color',
        'company_tax_id', 'pdf_logo',
    ];

    public const PUBLIC_KEYS = [
        'logo_light', 'logo_dark', 'favicon', 'sidebar_icon', 'app_title', 'footer_text',
        'primary_color', 'auth_background_image', 'auth_welcome_message', 'font_family',
        'meta_description', 'og_image', 'hide_watermark',
    ];

    public function getProtectedSettings(): array
    {
        return $this->resolveSettings(self::ALL_KEYS, 'protected');
    }

    public function getPublicSettings(): array
    {
        return $this->resolveSettings(self::PUBLIC_KEYS, 'public');
    }

    public function getAppTitle(): string
    {
        return $this->getPublicSettings()['app_title'] ?? 'HIVE.OS';
    }

    public function shouldHideWatermark(): bool
    {
        return (bool) ($this->getPublicSettings()['hide_watermark'] ?? false);
    }

    public function clearCache(): void
    {
        Cache::forget($this->brandSettingsCacheKey('protected'));
        Cache::forget($this->brandSettingsCacheKey('public'));
    }

    public function brandSettingsCacheKey(string $scope): string
    {
        $context = 'central';

        if (function_exists('tenant') && tenant('id')) {
            $context = 'tenant:' . tenant('id');
        }

        return "brand_settings:{$scope}:{$context}";
    }

    private function resolveSettings(array $keys, string $scope): array
    {
        $cacheKey = $this->brandSettingsCacheKey($scope);
        $resolved = Cache::rememberForever($cacheKey, function () use ($scope) {
            $keysToQuery = $scope === 'protected' ? self::ALL_KEYS : self::PUBLIC_KEYS;
            $settings = Setting::whereIn('key', $keysToQuery)->pluck('value', 'key');

            $data = [];
            foreach ($keysToQuery as $key) {
                $data[$key] = $settings[$key] ?? null;
            }

            return $this->applyDefaults($data);
        });

        $requested = [];
        foreach ($keys as $key) {
            $requested[$key] = $resolved[$key] ?? null;
        }

        return $requested;
    }

    private function applyDefaults(array $data): array
    {
        $data['app_title'] = trim((string) ($data['app_title'] ?? 'HIVE.OS')) ?: 'HIVE.OS';
        $data['footer_text'] = trim((string) ($data['footer_text'] ?? 'Powered by HIVE.OS')) ?: 'Powered by HIVE.OS';
        $data['primary_color'] = $data['primary_color'] ?? '#10b981';
        $data['document_header_color'] = $data['document_header_color'] ?? '#1e293b';
        $data['font_family'] = $data['font_family'] ?? 'Inter';
        $data['auth_welcome_message'] = $data['auth_welcome_message'] ?? 'Sign in to access your secure control hub.';
        $data['hide_watermark'] = filter_var($data['hide_watermark'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $data;
    }
}
