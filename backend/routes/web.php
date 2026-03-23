<?php

use Illuminate\Support\Facades\Route;

// Loop through your central domains (e.g., 'localhost', '127.0.0.1')
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {

        // All your central SaaS routes go inside here!
        Route::get('/', function () {
            return view('welcome');
        });
//         Route::get('/test-mailable', function () {
//     // Grab any user to test with
//     $user = \Modules\Identity\Models\User::first();

//     // Return the mailable directly to the browser
//     return new \Modules\Identity\Mail\UserCreated($user, 'fake-token-123', 'fake-password');
// });

Route::get('/test-export-logo', function () {
    $logoPath = \Modules\Core\Models\Setting::where('key', 'logo_dark')->value('value');

    // 1. Build the Standard URL (Used by React Print)
    $standardUrl = asset($logoPath);

    // 2. Build the Base64 string (Used by DOMPDF)
    $cleanPath = ltrim($logoPath, '/');
    if (str_starts_with($cleanPath, 'storage/')) {
        $cleanPath = substr($cleanPath, 8);
    }
    $fullPath = storage_path('app/public/' . $cleanPath);

    $base64 = null;
    if (file_exists($fullPath)) {
        $mime = mime_content_type($fullPath);
        $data = file_get_contents($fullPath);
        $base64 = 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    return response(
        "<div style='font-family: sans-serif; padding: 40px;'>" .
        "<h2>Test 1: The Browser Test (For the 'Print' Button)</h2>" .
        "<p>If the image below is broken, your React frontend cannot print it because Roadrunner/Laravel isn't serving the public storage files correctly (usually requires running <code>php artisan storage:link</code>).</p>" .
        "<div style='border: 2px dashed red; padding: 10px; display: inline-block;'>" .
        "<img src='{$standardUrl}' style='max-width: 200px;' alt='Broken Standard URL' />" .
        "</div>" .
        "<br><br><br>" .
        "<h2>Test 2: The Base64 Test (For the 'PDF' Button)</h2>" .
        "<p>If the image below is broken or missing, PHP is failing to read/encode the file, which means DOMPDF will fail to render it.</p>" .
        "<div style='border: 2px dashed green; padding: 10px; display: inline-block;'>" .
        ($base64 ? "<img src='{$base64}' style='max-width:200px;' alt='Base64 Success!'/>" : "<p style='color:red; font-weight:bold;'>Base64 Generation Failed!</p>") .
        "</div>" .
        "</div>"
    );
});
        // Example: Route::get('/pricing', ...);
        // Example: Route::post('/register-tenant', ...);

    });
}
