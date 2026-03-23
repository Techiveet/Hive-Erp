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
            ? "http://{$this->tenantId}.localhost:3000/sign-in"
            : "{$frontendUrl}/sign-in";

        $logoUrl = $this->resolveBrandLogoUrl();

        return new Content(
            view: 'core::emails.universal',
            with: [
                'title'         => 'Node Access Provisioned',
                'type'          => 'created',
                'message_intro' => 'Your tenant database has been deployed. You can now access your management gateway using the credentials below.',
                'actionUrl'     => $actionUrl,
                'actionText'    => 'Login to Tenant Gateway',
                'rawPassword'   => $this->rawPassword,
                'appName'       => 'HIVE.OS',
                'logoUrl'       => $logoUrl,
                'user'          => $this->user,
                'token'         => $this->token,
            ],
        );
    }

    protected function resolveBrandLogoUrl(): string
    {
        $fallback = 'https://techiveet.com/frontend/images/resources/logo1.png';

        // Fetch the string path from the settings cache
        $logoPath = cache()->remember(
            'mail_brand_logo_dark_path',
            now()->addHour(),
            fn () => Setting::where('key', 'logo_dark')->value('value')
        );

        if (empty($logoPath)) {
            return $fallback;
        }

        // Check if the setting is somehow already a full URL
        if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
            return $logoPath;
        }

        // The Fix: Just wrap the path in asset().
        // ltrim prevents double-slashes if your setting starts with a slash
        return asset(ltrim($logoPath, '/'));
    }
}
