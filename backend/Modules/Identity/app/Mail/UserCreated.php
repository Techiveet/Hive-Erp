<?php

namespace Modules\Identity\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\Setting;
use Modules\Identity\Models\User;
use Modules\Tenancy\Models\Tenant;

class UserCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token,
        public string $rawPassword,
        public ?string $tenantId = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to HIVE.OS - Node Provisioned'
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        $actionUrl = $this->tenantId
            ? $this->tenantSignInUrl($this->tenantId)
            : "{$frontendUrl}/sign-in";

        $logoUrl = $this->resolveBrandLogoUrl();

        return new Content(
            view: 'core::emails.universal',
            with: [
                'title' => 'Node Access Provisioned',
                'type' => 'created',
                'message_intro' => 'Your tenant database has been deployed. You can now access your management gateway using the credentials below.',
                'actionUrl' => $actionUrl,
                'actionText' => 'Login to Tenant Gateway',
                'rawPassword' => $this->rawPassword,
                'appName' => 'HIVE.OS',
                'logoUrl' => $logoUrl,
                'user' => $this->user,
                'token' => $this->token,
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
