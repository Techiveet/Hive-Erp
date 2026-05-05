<?php

namespace Modules\Tenancy\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\Setting;
use Modules\Identity\Models\User;
use Modules\Tenancy\Models\Tenant;

class AdminCredentialsUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public array $changes
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'HIVE.OS: Administrator Credentials Updated');
    }

    public function content(): Content
    {
        $tenantId = function_exists('tenant') ? tenant('id') : null;
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');
        $actionUrl = $tenantId ? $this->tenantSignInUrl((string) $tenantId) : "{$frontendUrl}/sign-in";
        $logoUrl = $this->resolveBrandLogoUrl();

        return new Content(
            view: 'core::emails.universal',
            with: [
                'title' => 'Credentials Updated',
                'type' => 'updated',
                'message_intro' => 'Your Super Admin access credentials for the tenant node have been modified by Central Command.',
                'changes' => $this->changes,
                'actionUrl' => $actionUrl,
                'actionText' => 'Login with New Credentials',
                'appName' => 'HIVE.OS',
                'logoUrl' => $logoUrl,
            ],
        );
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
