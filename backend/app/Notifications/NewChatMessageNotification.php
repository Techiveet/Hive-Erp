<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewChatMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const CHAT_E2EE_PREFIX = 'hive-e2ee:v1:';

    protected $sender;
    protected $message;
    protected $conversationId;

    /**
     * Create a new notification instance.
     */
    public function __construct($sender, $message, $conversationId)
    {
        $this->sender = $sender;
        $this->message = $message;
        $this->conversationId = $conversationId;
        
        $this->onConnection('redis')->onQueue('notifications');
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
        $messageBody = is_string($this->message->body) && str_starts_with($this->message->body, self::CHAT_E2EE_PREFIX)
            ? 'Sent an encrypted message'
            : ($this->message->body ?? 'sent a file');

        return [
            'category'        => 'chat',
            'title'           => 'New message from ' . $this->sender->name,
            'body'            => $messageBody,
            'url'             => '/dashboard/chat?id=' . $this->conversationId,
            'sender_id'       => $this->sender->id,
            'conversation_id' => $this->conversationId,
            'created_at'      => now()->toISOString(),
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
