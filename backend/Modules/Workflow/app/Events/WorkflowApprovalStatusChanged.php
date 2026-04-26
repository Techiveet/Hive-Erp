<?php

namespace Modules\Workflow\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Workflow\Models\WorkflowApproval;

class WorkflowApprovalStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?string $tenantId;

    public function __construct(
        public WorkflowApproval $approval,
        public string $oldStatus,
        public string $newStatus
    ) {
        $this->tenantId = function_exists('tenant') && tenant('id')
            ? (string) tenant('id')
            : null;
    }

    public function broadcastOn(): array
    {
        $channels = [];
        
        // Notify the requester
        if ($this->approval->requested_by) {
            $channels[] = new PrivateChannel('user.'.$this->approval->requested_by.'.workflow');
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
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
        ];
    }

    public function broadcastAs(): string
    {
        return 'workflow.approval.status_changed';
    }
}