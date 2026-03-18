<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    // 🚀 Good!
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    // 🚀 Exact matches go here
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ],

    // 🚀 THE FIX: Wildcard subdomains MUST use Regex patterns here!
    'allowed_origins_patterns' => [
        '#^http://([a-zA-Z0-9-]+\.)?localhost:3000$#'
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // 🚀 Perfect!
    'supports_credentials' => true,

];
