<?php

namespace Modules\MailBox\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\MailBox\Models\MailMessage;
use Modules\MailBox\Models\MailParticipant;
use Modules\MailBox\Events\MailReceived;

class DispatchMailToParticipants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message;
    protected array $userIds;
    protected string $type;
    protected $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(MailMessage $message, array $userIds, string $type, $tenantId = null)
    {
        $this->message = $message;
        $this->userIds = $userIds;
        $this->type = $type;
        $this->tenantId = $tenantId;

        // Force connection to Redis specifically to maximize performance
        $this->onConnection('redis');
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
            $participant = MailParticipant::create([
                'mail_message_id' => $this->message->id,
                'user_id'         => $recipientId,
                'type'            => $this->type,
                'folder'          => 'inbox',
                'is_read'         => false,
            ]);

            $participant->load(['message.sender', 'message.participants.user']);

            // Broadcast real-time presence
            broadcast(new MailReceived($participant->toArray(), $recipientId, $this->tenantId));
        }
    }
}
