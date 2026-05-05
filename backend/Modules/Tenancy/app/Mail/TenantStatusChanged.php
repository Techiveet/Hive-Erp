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

class TenantStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tenantName,
        public bool $isActive,
        public string $tenantId
    ) {}

    public function envelope(): Envelope
    {
        $status = $this->isActive ? 'Activated' : 'Suspended';

        return new Envelope(subject: "HIVE.OS: Your Workspace has been {$status}");
    }

    public function content(): Content
    {
        $statusText = $this->isActive ? 'activated' : 'suspended';
        $instruction = $this->isActive
            ? 'You may now log in and access your node.'
            : 'Please contact billing or support for more details.';
        $actionUrl = $this->isActive ? $this->tenantSignInUrl($this->tenantId) : null;
        $logoUrl = $this->resolveBrandLogoUrl();

        return new Content(
            view: 'core::emails.universal',
            with: [
                'title' => 'Workspace ' . ucfirst($statusText),
                'type' => 'notification',
                'message_intro' => "The workspace '{$this->tenantName}' has been {$statusText} by the Central Command. {$instruction}",
                'user' => (object) ['name' => 'Node Administrator'],
                'actionUrl' => $actionUrl,
                'actionText' => 'Access Node Gateway',
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
