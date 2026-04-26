<?php

namespace Modules\Chat\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?string $tenantId;

    public function __construct(
        public array $message,
        public array $conversation,
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
            'message' => $this->message,
            'conversation' => $this->conversation,
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }
}
