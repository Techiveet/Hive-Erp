<?php

use Illuminate\Support\Facades\Route;
use Modules\NightClub\Http\Controllers\NightClubController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('nightclubs', NightClubController::class)->names('nightclub');
});
