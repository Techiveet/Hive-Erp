<?php

use Illuminate\Support\Facades\Route;
use Modules\ProjectManagement\Http\Controllers\ProjectController;
use Modules\ProjectManagement\Http\Controllers\BoardController;
use Modules\ProjectManagement\Http\Controllers\TaskController;
use Modules\ProjectManagement\Http\Controllers\MemberController;

Route::middleware([
    \App\Http\Middleware\InitializeTenantContext::class,
    'auth:sanctum',
    'active_status',
    'dynamic_timeout',
    'tenant_module:project_management',
])
    ->prefix('v1/project-management')
    ->group(function () {
    // Projects
    Route::get('/summary', [ProjectController::class, 'summary']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    // Members
    Route::get('/users/search', [MemberController::class, 'searchUsers']);
    Route::post('/projects/{project}/members', [MemberController::class, 'store']);
    Route::delete('/projects/{project}/members/{user}', [MemberController::class, 'destroy']);

    // Boards & Columns
    Route::post('/boards', [BoardController::class, 'store']);
    Route::put('/boards/{board}', [BoardController::class, 'update']);
    Route::delete('/boards/{board}', [BoardController::class, 'destroy']);
    
    Route::post('/boards/{board}/columns', [BoardController::class, 'storeColumn']);
    Route::put('/columns/{column}', [BoardController::class, 'updateColumn']);
    Route::delete('/columns/{column}', [BoardController::class, 'destroyColumn']);

    // Tasks
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::post('/tasks/{task}/move', [TaskController::class, 'move']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    // Task Details
    Route::post('/tasks/{task}/checklists', [TaskController::class, 'storeChecklist']);
    Route::put('/checklists/{checklist}', [TaskController::class, 'updateChecklist']);
    Route::delete('/checklists/{checklist}', [TaskController::class, 'destroyChecklist']);

    Route::post('/tasks/{task}/comments', [TaskController::class, 'storeComment']);
});
