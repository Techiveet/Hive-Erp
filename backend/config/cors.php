<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // 🚀 THE FIX: Allow wildcard subdomains on port 3000
    'allowed_origins' => [
        'http://localhost:3000',     // Central App
        'http://127.0.0.1:3000',     // Central App IPv4
        'http://*.localhost:3000',   // ALL Tenant Subdomains (apple, nike, etc.)
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // 🚀 CRITICAL: This must be true for Sanctum authentication to work across origins
    'supports_credentials' => true,

];