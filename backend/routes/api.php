<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Global Fallback API Routes - HIVE.OS
|--------------------------------------------------------------------------
| All domain logic is now handled strictly inside /Modules/[Module]/routes/api.php
|
*/

Route::get('/unauthorized', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');
