<?php

namespace Modules\Subscription\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Subscription\Models\DemoRequest;

class DemoRequestSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DemoRequest $demoRequest,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('subscription.admin'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'demo.request.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->demoRequest->id,
            'first_name' => $this->demoRequest->first_name,
            'last_name' => $this->demoRequest->last_name,
            'email' => $this->demoRequest->email,
            'company' => $this->demoRequest->company,
            'company_size' => $this->demoRequest->company_size,
            'interests' => $this->demoRequest->interests,
            'created_at' => $this->demoRequest->created_at->toIso8601String(),
        ];
    }
}
