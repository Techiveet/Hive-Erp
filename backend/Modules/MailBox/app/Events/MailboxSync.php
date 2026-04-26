<?php

namespace Modules\MailBox\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Universal real-time sync pulse for Mailbox mutations.
 *
 * Fired whenever any significant mailbox action occurs (update, delete,
 * star, trash, bulk, etc.) so all active client sessions for that user
 * can reconcile their local state without a full HTTP fetch.
 */
class MailboxSync implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public ?string $tenantId;
    public string $action;     // 'update' | 'delete' | 'bulk' | 'sent'
    public array  $payload;    // action-specific data

    /**
     * @param int    $userId
     * @param string $action  One of: update, delete, bulk, sent
     * @param array  $payload Relevant IDs / fields that changed
     * @param string|null $tenantId
     */
    public function __construct(int $userId, string $action, array $payload, ?string $tenantId = null)
    {
        $this->userId   = $userId;
        $this->action   = $action;
        $this->payload  = $payload;
        $this->tenantId = $tenantId;
    }

    /**
     * Broadcast on the user's private mail channel.
     */
    public function broadcastOn(): array
    {
        $prefix = $this->tenantId ? "tenant.{$this->tenantId}." : '';
        return [
            new PrivateChannel("{$prefix}user.{$this->userId}.mail"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mail.sync';
    }

    public function broadcastWith(): array
    {
        return [
            'action'  => $this->action,
            'payload' => $this->payload,
        ];
    }
}
