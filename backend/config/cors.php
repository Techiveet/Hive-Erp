<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    // 🚀 THE FIX: Added 'storage/*' to the end of this array!
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    // 🚀 Allow wildcard subdomains on port 3000
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
