<?php

use Illuminate\Support\Facades\Route;

// Loop through your central domains (e.g., 'localhost', '127.0.0.1')
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        
        // All your central SaaS routes go inside here!
        Route::get('/', function () {
            return view('welcome');
        });
        
        // Example: Route::get('/pricing', ...);
        // Example: Route::post('/register-tenant', ...);

    });
}