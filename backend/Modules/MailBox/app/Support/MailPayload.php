<?php

namespace Modules\MailBox\Support;

use Modules\Core\Support\CommunicationEncryptionService;
use Modules\Identity\Models\User;
use Modules\MailBox\Models\MailParticipant;

class MailPayload
{
    private const E2EE_PREFIX = 'hive-e2ee:v1:';

    public static function participantForUser(MailParticipant $participant, User $user): array
    {
        $participant->loadMissing([
            'message.sender',
            'message.participants.user',
        ]);

        return [
            'id' => (int) $participant->id,
            'mail_message_id' => (int) $participant->mail_message_id,
            'user_id' => (int) $participant->user_id,
            'type' => $participant->type,
            'folder' => $participant->folder,
            'is_read' => (bool) $participant->is_read,
            'is_starred' => (bool) $participant->is_starred,
            'created_at' => optional($participant->created_at)->toISOString(),
            'message' => self::messageForUser($participant, $user),
        ];
    }

    private static function messageForUser(MailParticipant $participant, User $user): array
    {
        $message = $participant->message;

        return [
            'id' => (int) $message->id,
            'subject' => $message->subject,
            'body' => $message->body,
            'status' => $message->status,
            'draft_recipients' => $message->draft_recipients,
            'sender_id' => (int) $message->sender_id,
            'sender' => $message->sender ? self::userForPayload($message->sender) : null,
            'participants' => $message->participants
                ->map(fn (MailParticipant $recipient) => [
                    'user' => $recipient->user ? self::userForPayload($recipient->user) : null,
                ])
                ->values()
                ->all(),
            'created_at' => optional($message->created_at)->toISOString(),
            'encryption' => [
                'enabled' => app(CommunicationEncryptionService::class)->isEnabled(),
                'algorithm' => $participant->message_key_algorithm,
                'wrapped_key' => $participant->encrypted_message_key,
                'key_version' => $participant->message_key_version ? (int) $participant->message_key_version : null,
                'encrypted' => self::isEncryptedEnvelope($message->subject) || self::isEncryptedEnvelope($message->body),
                'subject_encrypted' => self::isEncryptedEnvelope($message->subject),
                'body_encrypted' => self::isEncryptedEnvelope($message->body),
            ],
        ];
    }

    public static function userForPayload(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_path' => $user->avatar_path,
            'avatar_url' => $user->avatar_url,
            'chat_public_key' => $user->chat_encryption_public_key,
        ];
    }

    private static function isEncryptedEnvelope(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::E2EE_PREFIX);
    }
}
