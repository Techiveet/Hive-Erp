<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewMailNotification extends Notification
{
    private const MAIL_E2EE_PREFIX = 'hive-e2ee:v1:';

    protected $senderName;
    protected $subject;
    protected $mailId;

    /**
     * Create a new notification instance.
     */
    public function __construct($senderName, $subject, $mailId)
    {
        $this->senderName = $senderName;
        $this->subject = $subject;
        $this->mailId = $mailId;
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
        $messageBody = is_string($this->subject) && str_starts_with($this->subject, self::MAIL_E2EE_PREFIX)
            ? 'Encrypted subject'
            : ($this->subject ?? '(No Subject)');

        return [
            'category'   => 'mail',
            'title'      => 'New Email from ' . $this->senderName,
            'body'       => $messageBody,
            'url'        => '/dashboard/mail?id=' . $this->mailId,
            'mail_id'    => $this->mailId,
            'created_at' => now()->toISOString(),
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
