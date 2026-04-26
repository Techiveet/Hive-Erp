<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Modules\Workflow\Models\WorkflowApproval;

class WorkflowStatusUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $approval;
    protected $actionedBy;
    protected $status;
    protected $subject;

    /**
     * Create a new notification instance.
     */
    public function __construct(WorkflowApproval $approval, string $status)
    {
        $this->approval = $approval;
        $this->status = $status;
        $this->actionedBy = $approval->user?->name ?? 'An approver';
        
        $model = $approval->approvable;
        $this->subject = 'your request';
        
        if ($model) {
            if (isset($model->name)) {
                $this->subject = $model->name;
            } elseif (isset($model->title)) {
                $this->subject = $model->title;
            } elseif (isset($model->reference)) {
                $this->subject = $model->reference;
            }
        }

        $this->onConnection('redis')->onQueue('default');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $verb = $this->status === 'approved' ? 'approved' : 'rejected';
        
        return [
            'category'    => 'workflow',
            'title'       => "Request " . ucfirst($verb),
            'body'        => "{$this->actionedBy} has {$verb} {$this->subject}.",
            'url'         => '/dashboard/workflow/approvals?tab=requested',
            'approval_id' => $this->approval->id,
            'created_at'  => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id'         => $this->id,
            'type'       => static::class,
            'data'       => $this->toArray($notifiable),
            'created_at' => now()->toISOString(),
        ]);
    }
}
