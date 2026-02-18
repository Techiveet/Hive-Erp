<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],

    // ✅ Fix: Explicitly allow the Next.js port
    'allowed_origins' => ['http://localhost:3000'],

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,

    // ✅ Fix: Must be true for Cookies/Auth to work
    'supports_credentials' => true,
];
