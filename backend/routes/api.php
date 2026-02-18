<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\UserExportController;

/*
|--------------------------------------------------------------------------
| Hive Universal API Routes
|--------------------------------------------------------------------------
*/

// 1. PUBLIC ROUTES (Available on Central & Tenant Domains)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/verify-2fa', [AuthController::class, 'verify2FA']);

// 2. PROTECTED ROUTES (Sanctum Required)
Route::middleware(['auth:sanctum'])->group(function () {

    // Identity & Session Management
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Export Engine (Excel/PDF)
    // Route::get('/users/export', [UserExportController::class, 'handleExport']);

    // Access Control (ACL)
    // Route::apiResource('permissions', PermissionController::class);
    // Route::apiResource('roles', RoleController::class);

    // User Management
    // ⚡ CUSTOM ACTION: Must be defined before the apiResource
    Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::apiResource('users', UserController::class);

    // Security: 2FA Neural Link
    Route::prefix('two-factor')->group(function () {
        Route::post('enable', [TwoFactorController::class, 'enable']);
        Route::post('confirm', [TwoFactorController::class, 'confirm']);
        Route::delete('disable', [TwoFactorController::class, 'disable']);
    });
});
