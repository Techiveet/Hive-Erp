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

class UserStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        $status = $this->user->is_active ? 'Activated' : 'Deactivated';

        return new Envelope(
            subject: "Hive Security: Network Access $status",
        );
    }

    public function content(): Content
    {
        $actionUrl = null;
        $actionText = null;

        if ($this->user->is_active) {
            $title = 'Access Restored';
            $messageIntro = 'Security alert: Your system identity has been ACTIVATED. You now have full access to the network and may authenticate.';

            $actionUrl = function_exists('tenant') && tenant('id')
                ? $this->tenantSignInUrl((string) tenant('id'))
                : rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/') . '/sign-in';
            $actionText = 'AUTHENTICATE NOW';
        } else {
            $title = 'Access Suspended';
            $messageIntro = 'Security alert: Your system identity has been DEACTIVATED. Your access to the network has been temporarily suspended.';
        }

        $logoUrl = $this->resolveBrandLogoUrl();

        return new Content(
            view: 'core::emails.universal',
            with: [
                'title' => $title,
                'type' => 'status',
                'message_intro' => $messageIntro,
                'actionUrl' => $actionUrl,
                'actionText' => $actionText,
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
