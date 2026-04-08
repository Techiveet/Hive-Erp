<?php

namespace Modules\Subscription\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Subscription\Models\TenantSubscriptionOrder;

class DirectTransferReviewSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public TenantSubscriptionOrder $order,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'direct_transfer_review',
            'title' => 'New direct transfer submitted for review',
            'body' => $this->mailBody(),
            'url' => $this->reviewUrl(),
            'order_id' => $this->order->id,
            'scope' => $this->order->scope,
            'tenant_id' => $this->order->tenant_id,
            'tenant_name' => $this->order->tenant_name,
            'admin_email' => $this->order->admin_email,
            'amount_etb' => (float) $this->order->total_amount_etb,
            'transaction_reference' => $this->order->manual_payment_reference,
            'bank_account' => $this->order->manual_payment_bank_account_snapshot,
            'review_url' => $this->reviewUrl(),
            'submitted_at' => optional($this->order->manual_payment_submitted_at)->toIso8601String(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))
            ->onConnection('sync');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('HIVE.OS direct transfer needs review')
            ->greeting('Hello ' . ($notifiable->name ?? 'Operator') . ',')
            ->line($this->mailBody())
            ->line('Tenant: ' . ($this->order->tenant_name ?: $this->order->tenant_id))
            ->line('Scope: ' . str($this->order->scope)->replace('_', ' ')->title())
            ->line('Amount: ETB ' . number_format((float) $this->order->total_amount_etb, 2))
            ->line('Transaction ID: ' . ($this->order->manual_payment_reference ?: 'Not provided'))
            ->action('Open Review Queue', $this->reviewUrl());
    }

    protected function mailBody(): string
    {
        $workspace = $this->order->tenant_name ?: $this->order->tenant_id ?: 'Pending workspace';

        return "A tenant subscription payment for {$workspace} was submitted through direct transfer and is waiting for manual verification.";
    }

    protected function reviewUrl(): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');

        return "{$frontendUrl}/dashboard/direct-transfer-reviews?order={$this->order->id}";
    }
}
