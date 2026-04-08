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
use Modules\Subscription\Models\TenantSubscription;
use Modules\Tenancy\Models\Tenant;

class TenantSubscriptionRenewalReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public TenantSubscription $subscription,
        public string $stage,
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
                'type' => 'updated',
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
        return match ($this->stage) {
            'expired' => "Hive Subscription Expired: [{$this->tenant->id}] requires renewal",
            'grace_period' => "Hive Subscription Grace Period: [{$this->tenant->id}] needs attention",
            default => "Hive Subscription Reminder: [{$this->tenant->id}] expires soon",
        };
    }

    protected function title(): string
    {
        return match ($this->stage) {
            'expired' => 'Workspace Subscription Expired',
            'grace_period' => 'Workspace In Grace Period',
            default => 'Workspace Renewal Reminder',
        };
    }

    protected function messageIntro(): string
    {
        return match ($this->stage) {
            'expired' => "Your workspace subscription for {$this->tenant->name} has expired. Renew it to restore full module access and keep the tenant operating normally.",
            'grace_period' => "Your workspace subscription for {$this->tenant->name} has entered its grace period. Renew now to avoid a full expiry lockout.",
            default => "Your workspace subscription for {$this->tenant->name} is approaching expiry. Renewing now will extend the billing window without interrupting access.",
        };
    }

    protected function actionText(): string
    {
        return match ($this->stage) {
            'expired' => 'Restore Subscription',
            'grace_period' => 'Renew During Grace Period',
            default => 'Renew Subscription',
        };
    }

    protected function changeLedger(): array
    {
        return [
            'Workspace' => $this->tenant->name ?? $this->tenant->id,
            'Plan' => Str::headline((string) $this->subscription->plan),
            'Status' => Str::headline((string) $this->subscription->status),
            'Expires At' => optional($this->subscription->expires_at)->toDayDateTimeString() ?? 'Pending',
            'Grace Ends At' => optional($this->subscription->grace_ends_at)->toDayDateTimeString() ?? 'Pending',
            'Renewal Mode' => Str::headline((string) $this->subscription->renewal_mode),
        ];
    }

    protected function actionUrl(): ?string
    {
        $domain = $this->tenant->domains->first()?->domain ?? "{$this->tenant->id}.localhost";
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $parsedUrl = parse_url($frontendUrl);
        $scheme = $parsedUrl['scheme'] ?? 'http';
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';

        return "{$scheme}://{$domain}{$port}/dashboard/subscriptions";
    }

    protected function recipientName(): string
    {
        if (!empty($this->tenant->admin_email) && str_contains((string) $this->tenant->admin_email, '@')) {
            return Str::headline(Str::before((string) $this->tenant->admin_email, '@'));
        }

        return $this->tenant->name ?? ucfirst($this->tenant->id);
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
