<?php

namespace Modules\Chat\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Chat\Models\Conversation;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?string $tenantId;

    public function __construct(
        public Conversation $conversation,
        public array $changes,
        public int $recipientId
    ) {
        $this->tenantId = function_exists('tenant') && tenant('id')
            ? (string) tenant('id')
            : null;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('user.'.$this->recipientId.'.chat'),
        ];

        if ($this->tenantId) {
            $channels[] = new PrivateChannel('tenant.'.$this->tenantId.'.user.'.$this->recipientId.'.chat');
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'action' => 'updated',
            'payload' => [
                'conversation_id' => $this->conversation->id,
                'changes' => $this->changes,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.conversation';
    }
}
