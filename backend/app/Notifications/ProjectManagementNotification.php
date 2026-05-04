<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ProjectManagementNotification extends Notification
{
    use Queueable;

    protected $data;
    protected $category;

    /**
     * Create a new notification instance.
     *
     * @param string $category The type of PM notification (e.g., pm_task_assigned, pm_task_comment)
     * @param array $data The payload containing title, body, url, etc.
     */
    public function __construct(string $category, array $data)
    {
        $this->category = $category;
        $this->data = $data;
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
        return array_merge([
            'category'   => $this->category,
            'created_at' => now()->toISOString(),
        ], $this->data);
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(array_merge([
            'id'         => $this->id,
            'type'       => static::class,
            'category'   => $this->category,
            'created_at' => now()->toISOString(),
        ], $this->data));
    }
}
