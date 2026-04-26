<?php

namespace Modules\Chat\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Core\Support\CommunicationEncryptionService;
use Modules\Identity\Models\User;

class ChatPayload
{
    public static function conversationForUser(Conversation $conversation, User $user): array
    {
        self::loadConversationRelations($conversation);
        $participant = $conversation->participants->firstWhere('id', $user->id);

        return [
            'id' => (int) $conversation->id,
            'type' => $conversation->type ?? 'private',
            'title' => $conversation->title,
            'avatar_path' => $conversation->avatar_path,
            'created_by' => $conversation->created_by ? (int) $conversation->created_by : null,
            'participants' => $conversation->participants
                ->map(fn (User $participant) => self::participantForPayload($participant))
                ->values()
                ->all(),
            'last_message' => $conversation->lastMessage
                ? self::messageSummary($conversation->lastMessage)
                : null,
            'unread_count' => self::unreadCountForUser($conversation, $user),
            'encryption' => [
                'enabled' => app(CommunicationEncryptionService::class)->isEnabled(),
                'algorithm' => $participant?->pivot?->conversation_key_algorithm,
                'wrapped_key' => $participant?->pivot?->encrypted_conversation_key,
                'key_version' => $participant?->pivot?->conversation_key_version
                    ? (int) $participant->pivot->conversation_key_version
                    : null,
            ],
            'updated_at' => optional($conversation->updated_at)->toISOString(),
        ];
    }

    public static function messageForUser(Message $message, User $user, ?Conversation $conversation = null): array
    {
        if (! $message->relationLoaded('sender')) {
            $message->load([
                'sender' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.chat_encryption_public_key');
                },
            ]);
        }

        $conversation ??= $message->relationLoaded('conversation')
            ? $message->conversation
            : Conversation::query()->find($message->conversation_id);

        if ($conversation) {
            self::loadConversationRelations($conversation);
        }

        return [
            'id' => (int) $message->id,
            'conversation_id' => (int) $message->conversation_id,
            'sender_id' => (int) $message->sender_id,
            'body' => $message->body,
            'type' => $message->type ?? 'text',
            'metadata' => self::metadataForUser($message, $conversation),
            'is_read' => self::messageReadStateForUser($message, $user, $conversation),
            'created_at' => optional($message->created_at)->toISOString(),
            'sender' => $message->sender
                ? self::participantForPayload($message->sender)
                : null,
        ];
    }

    public static function unreadCountForUser(Conversation $conversation, User $user): int
    {
        $lastReadAt = self::participantLastReadAt($conversation, $user->id);

        return $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->when($lastReadAt, fn ($query) => $query->where('created_at', '>', $lastReadAt))
            ->count();
    }

    public static function syncParticipantReadState(Conversation $conversation, int $userId, CarbonInterface $readAt): void
    {
        $conversation->participants()->updateExistingPivot($userId, [
            'last_read_at' => $readAt,
        ]);

        if ($conversation->relationLoaded('participants')) {
            $conversation->participants->each(function (User $participant) use ($userId, $readAt) {
                if ((int) $participant->id === (int) $userId && $participant->pivot) {
                    $participant->pivot->last_read_at = $readAt;
                }
            });
        }
    }

    public static function loadConversationRelations(Conversation $conversation): Conversation
    {
        $conversation->loadMissing([
            'participants' => function ($query) {
                $query->select('users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.chat_encryption_public_key');
            },
            'lastMessage.sender' => function ($query) {
                $query->select('users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.chat_encryption_public_key');
            },
        ]);

        return $conversation;
    }

    public static function messageSummary(Message $message): array
    {
        return [
            'id' => (int) $message->id,
            'body' => self::messagePreview($message->body, $message->metadata, $message->type ?? 'text'),
            'type' => $message->type ?? 'text',
            'metadata' => $message->metadata,
            'sender_id' => (int) $message->sender_id,
            'created_at' => optional($message->created_at)->toISOString(),
        ];
    }

    public static function messagePreview(?string $body, mixed $metadata, string $type): string
    {
        if (filled($body)) {
            return trim((string) $body);
        }

        $attachmentPreview = self::messagePreviewFromMetadata($metadata);
        if ($attachmentPreview) {
            return $attachmentPreview;
        }

        return self::messagePreviewForType($type);
    }

    private static function participantForPayload(User $participant): array
    {
        return [
            'id' => (int) $participant->id,
            'name' => $participant->name,
            'email' => $participant->email,
            'avatar_path' => $participant->avatar_path,
            'avatar_url' => $participant->avatar_url,
            'chat_public_key' => $participant->chat_encryption_public_key,
        ];
    }

    private static function messageReadStateForUser(Message $message, User $user, ?Conversation $conversation): bool
    {
        if (! $conversation) {
            return (bool) $message->is_read;
        }

        if ((int) $message->sender_id === (int) $user->id) {
            $otherParticipants = $conversation->participants->filter(
                fn (User $participant) => (int) $participant->id !== (int) $user->id
            );

            if ($otherParticipants->isEmpty()) {
                return false;
            }

            return $otherParticipants->every(function (User $participant) use ($conversation, $message) {
                $lastReadAt = self::participantLastReadAt($conversation, $participant->id);

                return $lastReadAt && $lastReadAt->greaterThanOrEqualTo($message->created_at);
            });
        }

        $lastReadAt = self::participantLastReadAt($conversation, $user->id);

        return $lastReadAt
            ? $lastReadAt->greaterThanOrEqualTo($message->created_at)
            : (bool) $message->is_read;
    }

    private static function participantLastReadAt(Conversation $conversation, int $userId): ?CarbonInterface
    {
        self::loadConversationRelations($conversation);

        /** @var \Modules\Identity\Models\User|null $participant */
        $participant = $conversation->participants->firstWhere('id', $userId);
        $lastReadAt = $participant?->pivot?->last_read_at;

        if (! $lastReadAt) {
            return null;
        }

        return $lastReadAt instanceof CarbonInterface
            ? $lastReadAt
            : Carbon::parse($lastReadAt);
    }

    private static function messagePreviewForType(string $type): string
    {
        return match ($type) {
            'image' => 'Sent an image',
            'file' => 'Sent a file',
            'audio' => 'Sent an audio message',
            default => 'New message',
        };
    }

    private static function messagePreviewFromMetadata(mixed $metadata): ?string
    {
        if (! is_array($metadata)) {
            return null;
        }

        $attachment = $metadata['attachment'] ?? null;
        if (! is_array($attachment)) {
            return null;
        }

        foreach (['title', 'name', 'download_name'] as $key) {
            $value = $attachment[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private static function metadataForUser(Message $message, ?Conversation $conversation): ?array
    {
        if (! is_array($message->metadata)) {
            return null;
        }

        $metadata = $message->metadata;
        $attachment = Arr::get($metadata, 'attachment');

        if (! $conversation || ! is_array($attachment)) {
            return $metadata;
        }

        $attachmentUrl = url(sprintf(
            '/api/v1/chat/conversations/%d/messages/%d/attachment',
            $conversation->id,
            $message->id
        ));

        $isImage = str_starts_with((string) ($attachment['mime_type'] ?? ''), 'image/')
            || ($attachment['type'] ?? null) === 'image'
            || ($message->type ?? null) === 'image';

        $metadata['attachment'] = array_merge($attachment, [
            'url' => $attachmentUrl,
            'thumbnail' => $isImage ? $attachmentUrl : null,
        ]);

        return $metadata;
    }
}
