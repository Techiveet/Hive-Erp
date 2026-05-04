<?php

use Illuminate\Support\Facades\Route;
use Modules\Hospitality\Http\Controllers\HospitalityDashboardController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('hospitality', HospitalityDashboardController::class)->names('hospitality');
});
