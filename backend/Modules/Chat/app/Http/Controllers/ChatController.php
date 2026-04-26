<?php

namespace Modules\Chat\Http\Controllers;

use App\Notifications\NewChatMessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Chat\Events\ConversationDeleted;
use Modules\Chat\Events\ConversationUpdated;
use Modules\Chat\Events\MessageSent;
use Modules\Chat\Events\MessagesRead;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Chat\Support\ChatPayload;
use Modules\Core\Models\FileEntry;
use Modules\Core\Support\CommunicationEncryptionService;
use Modules\Core\Support\TenantMediaStorage;
use Modules\Identity\Models\User;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ChatController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'encryption' => [
                'enabled' => app(CommunicationEncryptionService::class)->isEnabled(),
                'algorithm' => 'rsa-oaep-aes-gcm-v1',
                'public_key' => $user->chat_encryption_public_key,
                'fingerprint' => $user->chat_encryption_key_fingerprint,
            ],
        ]);
    }

    public function counts(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversations = $this->conversationQueryForUser($user)
            ->with([
                'participants' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.chat_encryption_public_key');
                },
            ])
            ->get();

        return response()->json([
            'total' => $conversations->count(),
            'unread' => $conversations->sum(fn (Conversation $conversation) => ChatPayload::unreadCountForUser($conversation, $user)),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = $this->conversationQueryForUser($user)
            ->when(
                in_array($request->string('type')->toString(), ['private', 'group'], true),
                fn ($query) => $query->where('type', $request->string('type')->toString())
            )
            ->with([
                'participants' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.chat_encryption_public_key');
                },
                'lastMessage.sender' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.chat_encryption_public_key');
                },
            ])
            ->latest('updated_at')
            ->paginate(20);

        $conversations->setCollection(
            $conversations->getCollection()->map(
                fn (Conversation $conversation) => ChatPayload::conversationForUser($conversation, $user)
            )
        );

        return response()->json($conversations);
    }

    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->findConversationForUser($user, $conversationId);

        ChatPayload::loadConversationRelations($conversation);

        $messages = $conversation->messages()
            ->with([
                'sender' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.chat_encryption_public_key');
                },
            ])
            ->latest('messages.created_at')
            ->paginate(50);

        $messages->setCollection(
            $messages->getCollection()
                ->sortBy('created_at')
                ->values()
                ->map(fn (Message $message) => ChatPayload::messageForUser($message, $user, $conversation))
        );

        return response()->json($messages);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'body' => 'nullable|string',
        ]);

        $user = $request->user();
        $otherUserId = (int) $request->integer('user_id');

        if ((int) $user->id === $otherUserId) {
            return response()->json(['error' => 'Cannot start conversation with yourself'], 400);
        }

        /** @var \Modules\Chat\Models\Conversation|null $conversation */
        $conversation = Conversation::query()
            ->where('type', 'private')
            ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
            ->whereHas('participants', fn ($query) => $query->where('users.id', $otherUserId))
            ->has('participants', '=', 2)
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'type' => 'private',
            ]);

            $conversation->participants()->attach([
                $user->id => [
                    'joined_at' => now(),
                    'last_read_at' => now(),
                ],
                $otherUserId => [
                    'joined_at' => now(),
                    'last_read_at' => null,
                ],
            ]);

            $conversation->unsetRelation('participants');
        }

        ChatPayload::loadConversationRelations($conversation);

        $messagePayload = null;

        if (filled($request->body)) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $request->string('body')->trim()->toString(),
                'type' => 'text',
                'is_read' => false,
            ]);

            $conversation->update(['updated_at' => $message->created_at]);
            ChatPayload::syncParticipantReadState($conversation, $user->id, $message->created_at);
            $conversation->load('lastMessage.sender');

            $recipient = User::find($otherUserId);
            if ($recipient) {
                event(new MessageSent(
                    ChatPayload::messageForUser($message->load('sender'), $recipient, $conversation),
                    ChatPayload::conversationForUser($conversation, $recipient),
                    $recipient->id
                ));

                try {
                    $recipient->notify(new NewChatMessageNotification($user, $message, $conversation->id));
                } catch (\Throwable $exception) {
                    \Log::error('Failed to send chat notification: '.$exception->getMessage());
                }
            }

            $messagePayload = ChatPayload::messageForUser($message, $user, $conversation);
        }

        return response()->json([
            'conversation' => ChatPayload::conversationForUser($conversation, $user),
            'message' => $messagePayload,
        ]);
    }

    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        $request->validate([
            'body' => 'nullable|string',
            'type' => 'required|string|in:text,image,file,audio',
            'metadata' => 'nullable|array',
        ]);

        if ($request->string('type')->toString() === 'text' && blank($request->body)) {
            return response()->json([
                'message' => 'Text messages cannot be empty.',
                'errors' => [
                    'body' => ['Text messages cannot be empty.'],
                ],
            ], 422);
        }

        $user = $request->user();
        $conversation = $this->findConversationForUser($user, $conversationId);

        ChatPayload::loadConversationRelations($conversation);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $request->body,
            'type' => $request->string('type')->toString(),
            'metadata' => $this->normalizeMessageMetadata($request->input('metadata'), $request->string('type')->toString()),
            'is_read' => false,
        ]);

        $conversation->update(['updated_at' => $message->created_at]);
        ChatPayload::syncParticipantReadState($conversation, $user->id, $message->created_at);
        $conversation->load('lastMessage.sender');

        $participants = $conversation->participants->filter(
            fn (User $participant) => (int) $participant->id !== (int) $user->id
        );

        foreach ($participants as $participant) {
            event(new MessageSent(
                ChatPayload::messageForUser($message->load('sender'), $participant, $conversation),
                ChatPayload::conversationForUser($conversation, $participant),
                $participant->id
            ));

            try {
                $participant->notify(new NewChatMessageNotification($user, $message, $conversation->id));
            } catch (\Throwable $exception) {
                \Log::error('Failed to send chat notification: '.$exception->getMessage());
            }
        }

        return response()->json([
            'message' => ChatPayload::messageForUser($message, $user, $conversation),
        ]);
    }

    public function markAsRead(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->findConversationForUser($user, $conversationId);

        ChatPayload::loadConversationRelations($conversation);

        $unreadCount = ChatPayload::unreadCountForUser($conversation, $user);
        $readAt = optional($conversation->lastMessage)->created_at ?? now();

        ChatPayload::syncParticipantReadState($conversation, $user->id, $readAt);

        if ($unreadCount > 0) {
            $participants = $conversation->participants->filter(
                fn (User $participant) => (int) $participant->id !== (int) $user->id
            );

            foreach ($participants as $participant) {
                event(new MessagesRead(
                    $conversation->id,
                    $user->id,
                    $participant->id
                ));
            }
        }

        return response()->json([
            'success' => true,
            'unread_cleared' => $unreadCount,
        ]);
    }

    public function deleteConversation(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();

        $conversation = $this->findConversationForUser($user, $conversationId);
        $conversation->participants()->detach($user->id);

        $remainingParticipants = $conversation->participants()->count();

        if ($remainingParticipants === 0) {
            $conversation->messages()->delete();
            $conversation->delete();
        } else {
            $participants = $conversation->participants()->get();

            foreach ($participants as $participant) {
                event(new ConversationUpdated(
                    $conversation,
                    ['participant_removed' => true, 'removed_by' => $user->id],
                    $participant->id
                ));
            }
        }

        return response()->json(['success' => true]);
    }

    public function bulkDeleteConversations(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:conversations,id',
        ]);

        $user = $request->user();
        $deletedCount = 0;

        foreach ($request->ids as $conversationId) {
            $conversation = $this->conversationQueryForUser($user)->find($conversationId);

            if (! $conversation) {
                continue;
            }

            $conversation->participants()->detach($user->id);

            if ($conversation->participants()->count() === 0) {
                $conversation->messages()->delete();
                $conversation->delete();
            } else {
                $participants = $conversation->participants()->get();

                foreach ($participants as $participant) {
                    event(new ConversationDeleted(
                        $conversationId,
                        $participant->id
                    ));
                }
            }

            $deletedCount++;
        }

        return response()->json([
            'success' => true,
            'deleted' => $deletedCount,
        ]);
    }

    public function serveAttachment(Request $request, int $conversationId, int $messageId)
    {
        $user = $request->user();
        $conversation = $this->findConversationForUser($user, $conversationId);
        $message = $conversation->messages()->findOrFail($messageId);

        [$attachment, $media] = $this->resolveAttachmentSource($message);

        $mediaStorage = app(TenantMediaStorage::class);
        $disk = $mediaStorage->mediaDisk($media);
        $relativePath = $mediaStorage->mediaRelativePath($media);

        if (! Storage::disk($disk)->exists($relativePath)) {
            abort(404, 'Attachment not found.');
        }

        $download = filter_var($request->query('download', false), FILTER_VALIDATE_BOOL);

        if ($download) {
            return Storage::disk($disk)->download(
                $relativePath,
                basename((string) ($attachment['download_name'] ?? $media->file_name ?? 'attachment')),
                [
                    'Content-Type' => $media->mime_type ?: 'application/octet-stream',
                    'Cache-Control' => 'private, no-store, max-age=0',
                ]
            );
        }

        return $mediaStorage->streamResponse($disk, $relativePath, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function saveAttachmentToFileManager(Request $request, int $conversationId, int $messageId): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->findConversationForUser($user, $conversationId);
        $message = $conversation->messages()->findOrFail($messageId);

        if (! is_array(Arr::get($message->metadata, 'attachment'))) {
            return response()->json([
                'message' => 'This message does not include a downloadable attachment.',
            ], 422);
        }

        [$attachment, $media, $sourceEntry] = $this->resolveAttachmentSource($message, true);
        $mediaStorage = app(TenantMediaStorage::class);

        $sourceFilename = basename((string) ($attachment['download_name'] ?? $media->file_name ?? 'chat-attachment'));
        $sourceTitle = trim((string) ($attachment['title'] ?? $attachment['name'] ?? $sourceEntry->resolveDisplayTitle($media)));
        $resolvedTitle = $sourceTitle !== '' ? $sourceTitle : (pathinfo($sourceFilename, PATHINFO_FILENAME) ?: 'Chat Attachment');

        $stagedFile = null;
        $stagedThumbnail = null;

        try {
            $stagedFile = $mediaStorage->stageMediaToLocalTemp($media);

            $savedEntry = FileEntry::create([
                'folder_id' => null,
                'user_id' => $user->id,
            ]);

            $savedEntry->addMedia($stagedFile)
                ->usingName($resolvedTitle)
                ->usingFileName($sourceFilename)
                ->toMediaCollection('file', $mediaStorage->mediaDisk());

            $customThumbnail = $sourceEntry->getFirstMedia('custom_thumbnail');
            if ($customThumbnail) {
                $stagedThumbnail = $mediaStorage->stageMediaToLocalTemp($customThumbnail);
                $savedEntry->addMedia($stagedThumbnail)
                    ->usingName($customThumbnail->name ?: $resolvedTitle)
                    ->usingFileName($customThumbnail->file_name ?: $sourceFilename)
                    ->toMediaCollection('custom_thumbnail', $mediaStorage->mediaDisk());
            }

            $this->forgetFileMetricsCacheForUser((int) $user->id);

            return response()->json([
                'message' => 'Attachment saved to File Manager.',
                'file' => $savedEntry->load('media'),
            ], 201);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Failed to save attachment to File Manager.',
            ], 500);
        } finally {
            if ($stagedFile && is_file($stagedFile)) {
                @unlink($stagedFile);
            }

            if ($stagedThumbnail && is_file($stagedThumbnail)) {
                @unlink($stagedThumbnail);
            }
        }
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $user = $request->user();

        try {
            $users = User::search($request->q)
                ->where('is_active', true)
                ->query(function ($query) use ($user) {
                    $query->where('id', '!=', $user->id)
                        ->select('id', 'name', 'email', 'avatar_path', 'chat_encryption_public_key');
                })
                ->take(15)
                ->get();
        } catch (\Throwable $exception) {
            $users = User::where('id', '!=', $user->id)
                ->where('is_active', true)
                ->where(function ($query) use ($request) {
                    $query->where('name', 'like', '%'.$request->q.'%')
                        ->orWhere('email', 'like', '%'.$request->q.'%');
                })
                ->select('id', 'name', 'email', 'avatar_path', 'chat_encryption_public_key')
                ->limit(15)
                ->get();
        }

        return response()->json(['data' => $users]);
    }

    public function updatePublicKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_key' => 'required|string|max:65535',
            'algorithm' => 'required|string|max:255',
            'fingerprint' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        $user->forceFill([
            'chat_encryption_public_key' => $validated['public_key'],
            'chat_encryption_key_algorithm' => $validated['algorithm'],
            'chat_encryption_key_fingerprint' => $validated['fingerprint'] ?? null,
        ])->save();

        return response()->json([
            'message' => 'Chat encryption identity updated.',
            'data' => [
                'public_key' => $user->chat_encryption_public_key,
                'algorithm' => $user->chat_encryption_key_algorithm,
                'fingerprint' => $user->chat_encryption_key_fingerprint,
            ],
        ]);
    }

    public function bootstrapConversationEncryption(Request $request, int $conversationId): JsonResponse
    {
        if (! app(CommunicationEncryptionService::class)->isEnabled()) {
            return response()->json([
                'message' => 'Communication encryption is disabled for this workspace.',
            ], 422);
        }

        $validated = $request->validate([
            'participant_keys' => 'required|array|min:1',
            'participant_keys.*' => 'required|string|max:65535',
        ]);

        $user = $request->user();
        $conversation = $this->findConversationForUser($user, $conversationId);
        ChatPayload::loadConversationRelations($conversation);

        $participantIds = $conversation->participants->pluck('id')->map(fn ($id) => (string) $id)->all();
        $participantKeyMap = collect($validated['participant_keys'])
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $value]);

        foreach ($participantIds as $participantId) {
            if (! $participantKeyMap->has($participantId)) {
                return response()->json([
                    'message' => 'Missing encryption material for one or more conversation participants.',
                ], 422);
            }
        }

        foreach ($conversation->participants as $participant) {
            $conversation->participants()->updateExistingPivot($participant->id, [
                'encrypted_conversation_key' => $participantKeyMap->get((string) $participant->id),
                'conversation_key_algorithm' => 'rsa-oaep-aes-gcm-v1',
                'conversation_key_version' => 1,
            ]);
        }

        $conversation->unsetRelation('participants');
        ChatPayload::loadConversationRelations($conversation);

        return response()->json([
            'message' => 'Conversation encryption initialized.',
            'data' => [
                'conversation' => ChatPayload::conversationForUser($conversation, $user),
            ],
        ]);
    }

    public function searchMessages(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $user = $request->user();
        $conversationIds = $user->conversations()->pluck('conversations.id')->toArray();

        try {
            $messages = Message::search($request->q)
                ->whereIn('conversation_id', $conversationIds)
                ->query(function ($query) {
                    $query->with(['sender:id,name,avatar_path', 'conversation:id,title,type']);
                })
                ->take(30)
                ->get();
        } catch (\Throwable $exception) {
            $searchTerm = Str::lower(trim((string) $request->q));

            $messages = Message::whereIn('conversation_id', $conversationIds)
                ->with(['sender:id,name,avatar_path', 'conversation:id,title,type'])
                ->latest('messages.created_at')
                ->limit(250)
                ->get()
                ->filter(function (Message $message) use ($searchTerm) {
                    $haystack = Str::lower(
                        trim(ChatPayload::messagePreview($message->body, $message->metadata, $message->type ?? 'text'))
                    );

                    return $haystack !== '' && Str::contains($haystack, $searchTerm);
                })
                ->take(30)
                ->values();
        }

        return response()->json(['data' => $messages]);
    }

    public function createGroup(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'avatar_path' => 'nullable|string',
        ]);

        $user = $request->user();
        $participantIds = array_values(array_unique(array_merge([$user->id], $request->user_ids)));

        $conversation = Conversation::create([
            'type' => 'group',
            'title' => $request->title,
            'avatar_path' => $request->avatar_path,
            'created_by' => $user->id,
        ]);

        $pivotData = [];
        foreach ($participantIds as $participantId) {
            $pivotData[$participantId] = [
                'joined_at' => now(),
                'last_read_at' => (int) $participantId === (int) $user->id ? now() : null,
            ];
        }

        $conversation->participants()->attach($pivotData);
        ChatPayload::loadConversationRelations($conversation);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $user->name.' created this group.',
            'type' => 'text',
            'is_read' => false,
        ]);

        $conversation->update(['updated_at' => $message->created_at]);
        ChatPayload::syncParticipantReadState($conversation, $user->id, $message->created_at);
        $conversation->load('lastMessage.sender');

        $participants = $conversation->participants->filter(
            fn (User $participant) => (int) $participant->id !== (int) $user->id
        );

        foreach ($participants as $participant) {
            event(new MessageSent(
                ChatPayload::messageForUser($message->load('sender'), $participant, $conversation),
                ChatPayload::conversationForUser($conversation, $participant),
                $participant->id
            ));
        }

        return response()->json([
            'conversation' => ChatPayload::conversationForUser($conversation, $user),
            'message' => ChatPayload::messageForUser($message, $user, $conversation),
        ]);
    }

    private function conversationQueryForUser(User $user)
    {
        return Conversation::query()->whereHas('participants', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        });
    }

    private function normalizeMessageMetadata(mixed $metadata, string $messageType): ?array
    {
        if (! is_array($metadata)) {
            return null;
        }

        $normalized = [];

        $replyTo = $metadata['reply_to'] ?? null;
        if (is_array($replyTo)) {
            $normalized['reply_to'] = [
                'id' => (int) Arr::get($replyTo, 'id', 0),
                'conversation_id' => (int) Arr::get($replyTo, 'conversation_id', 0),
                'sender_id' => (int) Arr::get($replyTo, 'sender_id', 0),
                'body' => Arr::get($replyTo, 'body'),
                'type' => $this->normalizeMessageType(Arr::get($replyTo, 'type')),
                'sender' => [
                    'id' => (int) Arr::get($replyTo, 'sender.id', 0),
                    'name' => Arr::get($replyTo, 'sender.name'),
                ],
            ];
        }

        $attachment = $metadata['attachment'] ?? null;
        if (is_array($attachment)) {
            $normalized['attachment'] = [
                'file_entry_id' => Arr::has($attachment, 'file_entry_id') ? (int) Arr::get($attachment, 'file_entry_id') : null,
                'uuid' => Arr::get($attachment, 'uuid'),
                'name' => Arr::get($attachment, 'name'),
                'title' => Arr::get($attachment, 'title'),
                'download_name' => Arr::get($attachment, 'download_name'),
                'mime_type' => Arr::get($attachment, 'mime_type'),
                'size' => Arr::has($attachment, 'size') ? (int) Arr::get($attachment, 'size') : null,
                'human_size' => Arr::get($attachment, 'human_size'),
                'url' => Arr::get($attachment, 'url'),
                'thumbnail' => Arr::get($attachment, 'thumbnail'),
                'type' => $this->normalizeMessageType(Arr::get($attachment, 'type', $messageType)),
            ];
        }

        return array_filter(
            $normalized,
            static fn (mixed $value): bool => is_array($value) && $value !== []
        ) ?: null;
    }

    private function findConversationForUser(User $user, int $conversationId): Conversation
    {
        return $this->conversationQueryForUser($user)->findOrFail($conversationId);
    }

    private function normalizeMessageType(mixed $type): string
    {
        return in_array($type, ['text', 'image', 'file', 'audio'], true)
            ? (string) $type
            : 'text';
    }

    /**
     * @return array{0: array<string, mixed>, 1: Media, 2?: FileEntry}
     */
    private function resolveAttachmentSource(Message $message, bool $includeFileEntry = false): array
    {
        $attachment = Arr::get($message->metadata, 'attachment');

        abort_unless(is_array($attachment), 404, 'Attachment not found.');

        $fileEntryId = (int) Arr::get($attachment, 'file_entry_id', 0);
        abort_unless($fileEntryId > 0, 404, 'Attachment source is unavailable.');

        $sourceEntry = FileEntry::with('media')->findOrFail($fileEntryId);
        abort_unless((int) $sourceEntry->user_id === (int) $message->sender_id, 404, 'Attachment source is unavailable.');

        $media = $sourceEntry->getFirstMedia('file');
        abort_unless($media instanceof Media, 404, 'Attachment file not found.');

        return $includeFileEntry
            ? [$attachment, $media, $sourceEntry]
            : [$attachment, $media];
    }

    private function forgetFileMetricsCacheForUser(int $userId): void
    {
        Cache::forget("file_metrics_{$this->currentTenantContextId()}_{$userId}");
    }

    private function currentTenantContextId(): string
    {
        return (function_exists('tenant') && tenant('id'))
            ? (string) tenant('id')
            : 'central';
    }
}
