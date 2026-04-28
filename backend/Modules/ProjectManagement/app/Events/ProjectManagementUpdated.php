<?php

namespace Modules\ProjectManagement\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectManagementUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?string $tenantId;

    public function __construct(
        public string $action,
        public array $payload = [],
        public ?string $projectId = null,
    ) {
        $this->tenantId = function_exists('tenant') && tenant('id')
            ? (string) tenant('id')
            : null;
    }

    public function broadcastOn(): array
    {
        $prefix = $this->tenantId ? "tenant.{$this->tenantId}." : '';

        $channels = [
            new PrivateChannel($prefix.'project-management'),
        ];

        if ($this->projectId) {
            $channels[] = new PrivateChannel($prefix.'project-management.project.'.$this->projectId);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'project_id' => $this->projectId,
            'payload' => $this->payload,
        ];
    }

    public function broadcastAs(): string
    {
        return 'project-management.updated';
    }
}
