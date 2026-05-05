<?php

namespace Modules\Core\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Setting;

trait ResolvesExportBranding
{
    protected function getExportBranding(bool $inlineLogo = false): array
    {
        $tenantPrefix = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
        $cacheKey = "export_branding_v1_{$tenantPrefix}_" . ($inlineLogo ? 'inline' : 'path');

        return Cache::remember($cacheKey, now()->addHour(), function () use ($inlineLogo) {
            $settings = Setting::whereIn('key', [
                'app_title',
                'footer_text',
                'document_header_color',
                'company_tax_id',
                'pdf_logo',
                'logo_dark',
            ])->pluck('value', 'key');

            $documentHeaderColor = $this->normalizeExportHexColor(
                $settings['document_header_color'] ?? '#1e293b',
                '#1e293b'
            );

            $logoSource = $settings['pdf_logo'] ?? $settings['logo_dark'] ?? null;

            return [
                'app_title' => trim((string) ($settings['app_title'] ?? 'HIVE.OS')) ?: 'HIVE.OS',
                'footer_text' => trim((string) ($settings['footer_text'] ?? 'Powered by HIVE.OS')) ?: 'Powered by HIVE.OS',
                'document_header_color' => $documentHeaderColor,
                'company_tax_id' => $this->normalizeExportText($settings['company_tax_id'] ?? null),
                'logo_url' => $this->resolveExportLogo($logoSource, $inlineLogo),
            ];
        });
    }

    protected function getResolvedLogo(bool $asBase64 = false): string
    {
        return $this->getExportBranding($asBase64)['logo_url'];
    }

    protected function normalizeExportHexColor(?string $value, string $fallback): string
    {
        $candidate = strtoupper(trim((string) $value));

        if (preg_match('/^#(?:[0-9A-F]{3}|[0-9A-F]{6})$/', $candidate)) {
            return $candidate;
        }

        return strtoupper($fallback);
    }

    protected function normalizeExportText(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function resolveExportLogo(?string $logoPath, bool $inlineLogo = false): string
    {
        $fallback = 'https://gulfingot.com/frontend/images/resources/logo1.png';

        if (empty($logoPath)) {
            return $fallback;
        }

        if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
            if ($inlineLogo) {
                try {
                    $data = file_get_contents($logoPath);
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: 'image/png';

                    return 'data:' . $mime . ';base64,' . base64_encode($data);
                } catch (\Throwable $e) {
                    return $logoPath;
                }
            }

            return $logoPath;
        }

        $cleanPath = ltrim($logoPath, '/');
        if (!str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = 'storage/' . $cleanPath;
        }

        $fullPath = public_path($cleanPath);
        $realPath = realpath($fullPath);

        if ($realPath && file_exists($realPath)) {
            if ($inlineLogo) {
                $mime = mime_content_type($realPath) ?: 'image/png';
                $data = file_get_contents($realPath);

                return 'data:' . $mime . ';base64,' . base64_encode($data);
            }

            return 'file://' . $realPath;
        }

        return $fallback;
    }
}

