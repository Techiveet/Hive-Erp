<?php

$toList = static function (?string $value): array {
    return array_values(array_filter(array_map(
        static fn ($item) => trim((string) $item),
        explode(',', (string) $value)
    )));
};

$normalizeOrigin = static function (?string $origin): ?string {
    $origin = trim((string) $origin);

    if ($origin === '' || !str_contains($origin, '://')) {
        return null;
    }

    $parts = parse_url($origin);

    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return null;
    }

    $normalized = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);

    if (!empty($parts['port'])) {
        $normalized .= ':' . $parts['port'];
    }

    return $normalized;
};

$allowedOrigins = array_values(array_unique(array_filter(array_map(
    $normalizeOrigin,
    array_merge(
        [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            (string) env('FRONTEND_URL', ''),
        ],
        $toList(env('CORS_ALLOWED_ORIGINS')),
    ),
))));

$allowedOriginPatterns = array_values(array_unique(array_filter(array_merge(
    [
        '#^http://([a-zA-Z0-9-]+\.)?localhost(:\d+)?$#',
    ],
    $toList(env('CORS_ALLOWED_ORIGIN_PATTERNS')),
))));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => $allowedOriginPatterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
