<?php

namespace Modules\MailBox\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\MailBox\Models\MailMessage;
use Modules\MailBox\Models\MailParticipant;
use Modules\MailBox\Events\MailReceived;
use Modules\MailBox\Support\MailPayload;
use App\Notifications\NewMailNotification;
use Modules\Identity\Models\User;

class DispatchMailToParticipants
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message;
    protected array $userIds;
    protected string $type;
    protected $tenantId;
    protected array $participantKeys;

    /**
     * Create a new job instance.
     */
    public function __construct(MailMessage $message, array $userIds, string $type, $tenantId = null, array $participantKeys = [])
    {
        $this->message = $message;
        $this->userIds = $userIds;
        $this->type = $type;
        $this->tenantId = $tenantId;
        $this->participantKeys = $participantKeys;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->userIds)) {
            return;
        }

        foreach ($this->userIds as $recipientId) {
            $wrappedKey = $this->participantKeys[(string) $recipientId] ?? null;
            $participant = MailParticipant::create([
                'mail_message_id' => $this->message->id,
                'user_id'         => $recipientId,
                'type'            => $this->type,
                'folder'          => 'inbox',
                'is_read'         => false,
                'encrypted_message_key' => $wrappedKey,
                'message_key_algorithm' => $wrappedKey ? 'rsa-oaep-aes-gcm-v1' : null,
                'message_key_version' => $wrappedKey ? 1 : null,
            ]);

            $participant->load(['message.sender', 'message.participants.user']);

            $recipient = User::find($recipientId);

            if (! $recipient) {
                continue;
            }

            // Broadcast real-time presence
            broadcast(new MailReceived(
                MailPayload::participantForUser($participant->load(['message.sender', 'message.participants.user']), $recipient),
                $recipientId,
                $this->tenantId
            ));

            // Send standard notification for bell icon
            if ($recipient) {
                $recipient->notify(new NewMailNotification(
                    $this->message->sender->name ?? 'System',
                    $this->message->subject,
                    $this->message->id
                ));
            }
        }
    }
}
