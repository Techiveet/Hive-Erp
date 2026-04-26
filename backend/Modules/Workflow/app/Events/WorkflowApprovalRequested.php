<?php

namespace Modules\Workflow\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Workflow\Models\WorkflowApproval;

class WorkflowApprovalRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?string $tenantId;

    public function __construct(
        public WorkflowApproval $approval
    ) {
        $this->tenantId = function_exists('tenant') && tenant('id')
            ? (string) tenant('id')
            : null;
    }

    public function broadcastOn(): array
    {
        $channels = [];
        
        if ($this->approval->user_id) {
            $channels[] = new PrivateChannel('user.'.$this->approval->user_id.'.workflow');
        }
        
        if ($this->approval->role_id) {
            $channels[] = new PrivateChannel('role.'.$this->approval->role_id.'.workflow');
        }

        if ($this->tenantId) {
            $channels[] = new PrivateChannel('tenant.'.$this->tenantId.'.workflow');
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'approval' => [
                'id' => $this->approval->id,
                'approvable_type' => $this->approval->approvable_type,
                'approvable_id' => $this->approval->approvable_id,
                'status' => $this->approval->status,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'workflow.approval.requested';
    }
}