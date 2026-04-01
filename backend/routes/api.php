<?php

use Modules\Core\Http\Controllers\ApiDocumentationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Global Fallback API Routes - HIVE.OS
|--------------------------------------------------------------------------
| All domain logic is now handled strictly inside /Modules/[Module]/routes/api.php
|
*/

Route::get('/docs', [ApiDocumentationController::class, 'index'])->name('api-docs.api-index');
Route::get('/docs/openapi.json', [ApiDocumentationController::class, 'spec'])->name('api-docs.api-spec');

Route::get('/unauthorized', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

Route::get('/test-broadcast', function () {
    $payload = [
        'id'          => rand(1000, 9999),
        'event'       => 'MANUAL_TEST',
        'description' => 'Incoming transmission from central command!',
        'causer'      => 'System Admin',
        'time'        => now()->format('H:i:s'),
    ];

    // Fire the event directly to the 'central' tenant channel
    event(new \Modules\Core\Events\DashboardActivityLogged($payload, 'central'));

    return response()->json([
        'status' => 'Broadcast dispatched to Reverb!',
        'payload' => $payload
    ]);
});

