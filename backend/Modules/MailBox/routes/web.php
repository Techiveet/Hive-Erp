<?php

use Illuminate\Support\Facades\Route;
use Modules\MailBox\Http\Controllers\MailBoxController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('mailboxes', MailBoxController::class)->names('mailbox');
});
