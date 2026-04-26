<?php

use Illuminate\Support\Facades\Route;
use Modules\Workflow\Http\Controllers\WorkflowController;
use Modules\Workflow\Http\Controllers\WorkflowApprovalController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('workflows', WorkflowController::class)->names('workflow');

    Route::get('workflow-approvals', [WorkflowApprovalController::class, 'index']);
    Route::post('workflow-approvals', [WorkflowApprovalController::class, 'store']);
    Route::put('workflow-approvals/{approval}', [WorkflowApprovalController::class, 'update']);

    Route::get('workflow-definitions', [WorkflowApprovalController::class, 'getDefinitions']);
    Route::post('workflow-definitions', [WorkflowApprovalController::class, 'storeDefinition']);
    Route::delete('workflow-definitions/{definition}', [WorkflowApprovalController::class, 'destroyDefinition']);

    Route::get('approval-roles', [WorkflowApprovalController::class, 'getApprovalRoles']);
    Route::post('approval-roles', [WorkflowApprovalController::class, 'storeApprovalRole']);
    Route::put('approval-roles/{approvalRole}', [WorkflowApprovalController::class, 'updateApprovalRole']);
    Route::delete('approval-roles/{approvalRole}', [WorkflowApprovalController::class, 'destroyApprovalRole']);
    Route::get('approval-roles/available-users', [WorkflowApprovalController::class, 'getAvailableUsers']);
    Route::post('approval-roles/{approvalRole}/sync-users', [WorkflowApprovalController::class, 'syncUsers']);
});
