<?php

use Illuminate\Support\Facades\Route;
use Modules\Workflow\Http\Controllers\WorkflowController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('workflows', WorkflowController::class)->names('workflow');
});
