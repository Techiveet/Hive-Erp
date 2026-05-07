<?php

namespace Modules\Subscription\Notifications;

use Illuminate\Notifications\Notification;
use Modules\Subscription\Models\DemoRequest;

class DemoRequestSubmittedNotification extends Notification
{
    public function __construct(
        public DemoRequest $demoRequest,
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'demo_request',
            'category' => 'demo',
            'title' => 'New Demo Request',
            'body' => "{$this->demoRequest->first_name} {$this->demoRequest->last_name} from {$this->demoRequest->company} requested a demo.",
            'url' => '/dashboard/subscriptions/demo-requests',
            'demo_request_id' => $this->demoRequest->id,
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function broadcastType(): string
    {
        return 'demo.request.submitted';
    }
}
