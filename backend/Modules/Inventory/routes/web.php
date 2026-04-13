<?php

use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/inventory', fn () => view('inventory::index'));
});
