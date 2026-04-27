<?php

use Modules\Core\Http\Controllers\ApiDocumentationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Models\Domain;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['prefix' => 'api/v1', 'middleware' => ['api', 'auth:sanctum']]);

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

Route::get('/internal/caddy/allow-domain', function (Request $request) {
    $domain = strtolower(trim((string) $request->query('domain', '')));

    if ($domain === '') {
        abort(403);
    }

    if (filter_var($domain, FILTER_VALIDATE_IP)) {
        abort(403);
    }

    if (! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        abort(403);
    }

    $allowed = Domain::query()
        ->where('domain', $domain)
        ->exists();

    abort_unless($allowed, 403);

    return response()->noContent();
});

Route::get('/test-broadcast', function () {
    abort_unless(app()->environment(['local', 'testing']), 404);

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

