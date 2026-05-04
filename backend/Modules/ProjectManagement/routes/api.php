<?php

use Modules\ProjectManagement\Http\Controllers\ProjectAutomationController;
use Illuminate\Support\Facades\Route;
use Modules\ProjectManagement\Http\Controllers\ProjectController;
use Modules\ProjectManagement\Http\Controllers\BoardController;
use Modules\ProjectManagement\Http\Controllers\TaskController;
use Modules\ProjectManagement\Http\Controllers\MemberController;
use Modules\ProjectManagement\Http\Controllers\ProjectCommentController;
use Modules\ProjectManagement\Http\Controllers\ProjectGoalController;
use Modules\ProjectManagement\Http\Controllers\TaskTimeLogController;
use Modules\ProjectManagement\Http\Controllers\SprintController;
use Modules\ProjectManagement\Http\Controllers\ProjectFinancialController;

Route::middleware([
    \App\Http\Middleware\InitializeTenantContext::class,
    'auth:sanctum',
    'active_status',
    'dynamic_timeout',
    'tenant_module:project_management',
])
    ->prefix('v1/project-management')
    ->group(function () {
    // Project Goals
    Route::get('/projects/{project}/goals', [ProjectGoalController::class, 'index']);
    Route::post('/projects/{project}/goals', [ProjectGoalController::class, 'store']);
    Route::put('/goals/{goal}', [ProjectGoalController::class, 'update']);
    Route::delete('/goals/{goal}', [ProjectGoalController::class, 'destroy']);

    // Projects
    Route::get('/test-ping', function() { return response()->json(['status' => 'ok']); });
    Route::get('/summary', [ProjectController::class, 'summary']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::get('/templates', [ProjectController::class, 'templates']);
    Route::post('/projects/{project}/spawn', [ProjectController::class, 'spawn']);

    // Members
    Route::get('/users/search', [MemberController::class, 'searchUsers']);
    Route::get('/members/global-workload', [MemberController::class, 'getGlobalWorkload']);
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
    Route::get('/my-tasks', [TaskController::class, 'myTasks']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::post('/tasks/{task}/move', [TaskController::class, 'move']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    // Task Details
    Route::post('/tasks/{task}/checklists', [TaskController::class, 'storeChecklist']);
    Route::put('/checklists/{checklist}', [TaskController::class, 'updateChecklist']);
    Route::delete('/checklists/{checklist}', [TaskController::class, 'destroyChecklist']);
    Route::post('/attachments/{attachment}/review', [ProjectController::class, 'reviewAttachment']);

    Route::post('/tasks/{task}/comments', [TaskController::class, 'storeComment']);
    Route::post('/task-comments/bulk-delete', [TaskController::class, 'bulkDestroyComments']);
    Route::put('/task-comments/{comment}', [TaskController::class, 'updateComment']);
    Route::delete('task-comments/{comment}', [TaskController::class, 'destroyComment']);

    // Automations
    Route::get('/projects/{project}/automations', [ProjectAutomationController::class, 'index']);
    Route::post('/projects/{project}/automations', [ProjectAutomationController::class, 'store']);
    Route::put('/automations/{automation}', [ProjectAutomationController::class, 'update']);
    Route::delete('/automations/{automation}', [ProjectAutomationController::class, 'destroy']);

    // Task Time Logs
    Route::get('/tasks/{task}/time-logs', [TaskTimeLogController::class, 'index']);
    Route::post('/tasks/{task}/time-logs', [TaskTimeLogController::class, 'store']);
    Route::post('/tasks/{task}/time-logs/start', [TaskTimeLogController::class, 'start']);
    Route::post('/time-logs/{timeLog}/stop', [TaskTimeLogController::class, 'stop']);
    Route::delete('/time-logs/{timeLog}', [TaskTimeLogController::class, 'destroy']);
    Route::get('/time-logs/active', [TaskTimeLogController::class, 'active']);

    // Project Comments (Discussions)
    Route::get('/projects/{project}/comments', [ProjectCommentController::class, 'index']);
    Route::post('/projects/{project}/comments', [ProjectCommentController::class, 'store']);
    Route::post('/comments/bulk-delete', [ProjectCommentController::class, 'bulkDestroy']);
    Route::put('/comments/{comment}', [ProjectCommentController::class, 'update']);
    Route::delete('/comments/{comment}', [ProjectCommentController::class, 'destroy']);

    // Sprints
    Route::post('/projects/{project}/sprints', [SprintController::class, 'store']);
    Route::put('/sprints/{sprint}', [SprintController::class, 'update']);
    Route::delete('/sprints/{sprint}', [SprintController::class, 'destroy']);
    Route::post('/sprints/{sprint}/start', [SprintController::class, 'start']);
    Route::post('/sprints/{sprint}/complete', [SprintController::class, 'complete']);

    // Financial Reports
    Route::get('/projects/{project}/financial-report', [ProjectFinancialController::class, 'report']);
});
