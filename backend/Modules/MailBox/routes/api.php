<?php

use Illuminate\Support\Facades\Route;
use Modules\MailBox\Http\Controllers\MailBoxController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('mail/unread-count', [MailBoxController::class, 'unreadCount'])->name('mailbox.unread');
    Route::apiResource('mail', MailBoxController::class)->names('mailbox');
});
