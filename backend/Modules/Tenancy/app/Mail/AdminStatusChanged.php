<?php

namespace Modules\Tenancy\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\Setting;
use Modules\Tenancy\Models\Tenant;

class AdminStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public object $user,
        public bool $isActive,
        public string $tenantName
    ) {}

    public function envelope(): Envelope
    {
        $status = $this->isActive ? 'Restored' : 'Suspended';

        return new Envelope(subject: "HIVE.OS: Administrator Access {$status}");
    }

    public function content(): Content
    {
        $statusText = $this->isActive ? 'restored' : 'suspended';
        $instruction = $this->isActive
            ? 'You may now log in to manage your node.'
            : 'Please contact Central Command for further details.';

        $tenantId = function_exists('tenant') ? tenant('id') : null;
        $actionUrl = $tenantId ? $this->tenantSignInUrl((string) $tenantId) : null;
        $logoUrl = $this->resolveBrandLogoUrl();

        return new Content(
            view: 'core::emails.universal',
            with: [
                'title' => 'Access ' . ucfirst($statusText),
                'type' => 'notification',
                'user' => $this->user,
                'message_intro' => "Your Super Admin access for the '{$this->tenantName}' node has been {$statusText}. {$instruction}",
                'actionUrl' => $this->isActive ? $actionUrl : null,
                'actionText' => 'Login to Tenant Gateway',
                'appName' => 'HIVE.OS',
                'logoUrl' => $logoUrl,
            ],
        );
    }

    protected function resolveBrandLogoUrl(): string
    {
        $fallback = 'https://gulfingot.com/frontend/images/resources/logo1.png';

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

    protected function tenantSignInUrl(string $tenantId): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');
        $tenant = Tenant::query()->with('domains')->find($tenantId);
        $tenantDomain = $tenant?->primaryDomain()?->domain;

        if ($tenantDomain) {
            $scheme = parse_url($frontendUrl, PHP_URL_SCHEME) ?: 'http';

            return "{$scheme}://{$tenantDomain}/sign-in";
        }

        $host = parse_url($frontendUrl, PHP_URL_HOST) ?: 'localhost';
        $scheme = parse_url($frontendUrl, PHP_URL_SCHEME) ?: 'http';
        $fallbackHost = $host === 'localhost' ? "{$tenantId}.localhost" : "{$tenantId}.{$host}";

        return "{$scheme}://{$fallbackHost}/sign-in";
    }
}
