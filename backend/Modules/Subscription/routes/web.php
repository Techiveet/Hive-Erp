<?php

use Illuminate\Support\Facades\Route;
use Modules\Subscription\Http\Controllers\TenancyController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('tenancies', TenancyController::class)->names('subscription');
});

