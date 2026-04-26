<?php

use Illuminate\Support\Facades\Route;
use Modules\Chat\Http\Controllers\ChatController;

Route::middleware([
    'auth:sanctum',
    'active_status',
    'dynamic_timeout',
    'tenant_module:mailbox',
    'permission:view_chat|manage_chat,sanctum',
])->prefix('v1')->group(function () {
    Route::get('chat/counts', [ChatController::class, 'counts'])->name('chat.counts');
    Route::get('chat/config', [ChatController::class, 'config'])->name('chat.config');
    Route::get('chat/messages/search', [ChatController::class, 'searchMessages'])->name('chat.messages.search');
    Route::get('chat/conversations', [ChatController::class, 'conversations'])->name('chat.conversations');
    Route::get('chat/conversations/{conversationId}/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::put('chat/conversations/{conversationId}/read', [ChatController::class, 'markAsRead'])->name('chat.messages.read');
    Route::get('chat/conversations/{conversationId}/shared', [ChatController::class, 'getSharedItems'])->name('chat.conversations.shared');
    Route::post('chat/encryption/public-key', [ChatController::class, 'updatePublicKey'])->name('chat.encryption.public-key');
    Route::post('chat/conversations/{conversationId}/encryption/bootstrap', [ChatController::class, 'bootstrapConversationEncryption'])
        ->name('chat.conversations.encryption.bootstrap');
    Route::get('chat/conversations/{conversationId}/messages/{messageId}/attachment', [ChatController::class, 'serveAttachment'])->name('chat.messages.attachment');
    Route::post('chat/conversations/{conversationId}/messages/{messageId}/save-attachment', [ChatController::class, 'saveAttachmentToFileManager'])
        ->middleware('permission:manage_storage,sanctum')
        ->name('chat.messages.attachment.save');

    Route::middleware('permission:manage_chat,sanctum')->group(function () {
        Route::get('chat/users/search', [ChatController::class, 'searchUsers'])->name('chat.users.search');
        Route::post('chat/conversations', [ChatController::class, 'createConversation'])->name('chat.conversations.create');
        Route::post('chat/conversations/bulk-delete', [ChatController::class, 'bulkDeleteConversations'])->name('chat.conversations.bulk-delete');
        Route::post('chat/groups', [ChatController::class, 'createGroup'])->name('chat.groups.create');
        Route::delete('chat/conversations/{conversationId}', [ChatController::class, 'deleteConversation'])->name('chat.conversations.delete');
        Route::post('chat/conversations/{conversationId}/messages', [ChatController::class, 'sendMessage'])->name('chat.messages.send');
    });
});
