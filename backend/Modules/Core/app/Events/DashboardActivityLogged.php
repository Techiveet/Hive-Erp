<?php

namespace Modules\Core\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardActivityLogged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $activity;
    public $tenantId;

    public function __construct($activity, $tenantId)
    {
        $this->activity = $activity;
        $this->tenantId = $tenantId;
    }

    public function broadcastOn(): array
    {
        // Broadcasts to a secure private channel
        return [
            new PrivateChannel('dashboard.' . strtolower($this->tenantId)),
        ];
    }

    public function broadcastAs(): string
    {
        // The specific event name the frontend will listen to
        return 'activity.logged';
    }

    /**
     * Get the data to broadcast.
     * This shapes the JSON payload sent to the React frontend.
     */
    public function broadcastWith(): array
    {
        // 1. If you are passing an Eloquent Model (like Spatie ActivityLog)
        if (is_object($this->activity)) {
            return [
                'activity' => [
                    'id' => $this->activity->id ?? rand(100, 999),
                    'event' => $this->activity->event ?? 'deleted',
                    'description' => $this->activity->description ?? 'deleted',
                    // This is the magic line that extracts "User", "Role", etc.
                    'subject_type' => isset($this->activity->subject_type)
                        ? class_basename($this->activity->subject_type)
                        : '',
                    'causer' => $this->activity->causer ? $this->activity->causer->name : 'System',
                    'time' => $this->activity->created_at ? $this->activity->created_at->diffForHumans() : 'Just now',
                ],
                'tenantId' => $this->tenantId,
            ];
        }

        // 2. If you are passing an Array to the Event from your Controller
        $payload = is_array($this->activity) ? $this->activity : [];

        return [
            'activity' => $payload,
            'tenantId' => $this->tenantId,
        ];
    }
}
