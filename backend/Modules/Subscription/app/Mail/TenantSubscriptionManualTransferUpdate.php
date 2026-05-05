<?php

namespace Modules\Subscription\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Modules\Core\Models\Setting;
use Modules\Subscription\Models\TenantSubscriptionOrder;

class TenantSubscriptionManualTransferUpdate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantSubscriptionOrder $order,
        public string $outcome,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'core::emails.universal',
            with: [
                'title' => $this->title(),
                'type' => $this->outcome === 'approved' ? 'created' : 'updated',
                'message_intro' => $this->messageIntro(),
                'changes' => $this->changeLedger(),
                'actionUrl' => $this->actionUrl(),
                'actionText' => $this->actionText(),
                'appName' => 'HIVE.OS',
                'logoUrl' => $this->resolveBrandLogoUrl(),
                'user' => (object) ['name' => $this->recipientName()],
            ],
        );
    }

    protected function subjectLine(): string
    {
        return $this->outcome === 'approved'
            ? "Hive Manual Transfer Approved: [{$this->order->tenant_id}] is now active"
            : "Hive Manual Transfer Needs Attention: [{$this->order->tenant_id}] reference mismatch";
    }

    protected function title(): string
    {
        return $this->outcome === 'approved'
            ? 'Direct Transfer Approved'
            : 'Direct Transfer Reference Mismatch';
    }

    protected function messageIntro(): string
    {
        if ($this->outcome === 'approved') {
            return match ($this->order->scope) {
                'tenant_renewal' => 'Your bank transfer has been verified and the workspace subscription renewal is now active.',
                'tenant_upgrade' => 'Your bank transfer has been verified and the requested tenant modules are now active.',
                default => 'Your bank transfer has been verified and your workspace is ready. Use the action below to sign in.',
            };
        }

        $defaultMessage = 'We could not match the submitted transaction reference against the bank transfer that reached our account. Please check the reference and submit it again.';

        return filled($this->order->manual_review_notes)
            ? (string) $this->order->manual_review_notes
            : $defaultMessage;
    }

    protected function actionText(): string
    {
        return $this->outcome === 'approved'
            ? (match ($this->order->scope) {
                'tenant_renewal', 'tenant_upgrade' => 'Open Subscription Workspace',
                default => 'Continue to Sign In',
            })
            : 'Submit a New Reference';
    }

    protected function actionUrl(): ?string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');

        if ($this->order->scope === 'public_signup') {
            return "{$frontendUrl}/auth/signup";
        }

        $domain = $this->order->tenant_domain ?: "{$this->order->tenant_id}.localhost";
        $parsed = parse_url($frontendUrl);
        $scheme = $parsed['scheme'] ?? 'http';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        if ($this->outcome === 'rejected') {
            return "{$scheme}://{$domain}{$port}/dashboard/subscriptions";
        }

        return "{$scheme}://{$domain}{$port}/dashboard/subscriptions";
    }

    protected function changeLedger(): array
    {
        return [
            'Workspace' => $this->order->tenant_name ?: ($this->order->tenant_id ?: 'Pending Workspace'),
            'Scope' => Str::headline((string) $this->order->scope),
            'Plan' => Str::headline((string) $this->order->plan),
            'Amount' => 'ETB ' . number_format((float) $this->order->total_amount_etb, 2),
            'Submitted Reference' => $this->order->manual_payment_reference ?: 'Not provided',
            'Reviewed At' => optional($this->order->manual_reviewed_at)->toDayDateTimeString() ?: 'Pending',
            'Review Outcome' => Str::headline($this->outcome),
            'Reviewer Message' => $this->order->manual_review_notes ?: 'No extra note provided.',
        ];
    }

    protected function recipientName(): string
    {
        if (!empty($this->order->admin_name)) {
            return (string) $this->order->admin_name;
        }

        if (!empty($this->order->admin_email) && str_contains((string) $this->order->admin_email, '@')) {
            return Str::headline(Str::before((string) $this->order->admin_email, '@'));
        }

        return $this->order->tenant_name ?: 'Workspace Admin';
    }

    protected function resolveBrandLogoUrl(): string
    {
        $fallback = 'https://techiveet.com/frontend/images/resources/logo1.png';

        $logoPath = cache()->remember(
            'mail_brand_logo_dark_path',
            now()->addHour(),
            fn () => Setting::where('key', 'logo_dark')->value('value')
        );

        if (empty($logoPath)) {
            return $fallback;
        }

        if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
            return $logoPath;
        }

        return asset(ltrim($logoPath, '/'));
    }
}
