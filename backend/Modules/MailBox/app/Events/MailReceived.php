<?php

namespace Modules\MailBox\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MailReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $participantData;
    public $userId;
    public $tenantId;

    /**
     * Create a new event instance.
     */
    public function __construct(array $participantData, $userId, $tenantId = null) 
    {
        $this->participantData = $participantData;
        $this->userId = $userId;
        $this->tenantId = $tenantId;
    }

    /**
     * Get the channels the event should be broadcast on.
     */
    public function broadcastOn(): array
    {
        $prefix = $this->tenantId ? "tenant.{$this->tenantId}." : "";
        return [
            new PrivateChannel($prefix . 'user.' . $this->userId . '.mail'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mail.received';
    }
}
