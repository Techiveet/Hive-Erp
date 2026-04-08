<?php

use Illuminate\Support\Facades\Route;
use Modules\MailBox\Http\Controllers\MailBoxController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('mail/unread-count', [MailBoxController::class, 'unreadCount'])->name('mailbox.unread');
    Route::get('mail/counts', [MailBoxController::class, 'counts'])->name('mailbox.counts');
    Route::post('mail/bulk', [MailBoxController::class, 'bulkAction'])->name('mailbox.bulk');
    Route::apiResource('mail', MailBoxController::class)
        ->parameters(['mail' => 'id'])
        ->where(['id' => '[0-9]+'])
        ->names('mailbox');
});
