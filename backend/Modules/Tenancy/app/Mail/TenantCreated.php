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

class TenantCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public User $admin,
        public string $rawPassword,
        public ?string $token = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to HIVE.OS - Node [{$this->tenant->id}] Provisioned"
        );
    }

    public function content(): Content
    {
        // Resolve the tenant's specific domain for the login link
        $domain = $this->tenant->domains->first()?->domain ?? "{$this->tenant->id}.localhost";

        // Dynamically build the URL based on your frontend config
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $parsedUrl = parse_url($frontendUrl);
        $scheme = $parsedUrl['scheme'] ?? 'http';
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';

        $actionUrl = "{$scheme}://{$domain}{$port}/sign-in";

        $logoUrl = $this->resolveBrandLogoUrl();

        return new Content(
            view: 'core::emails.universal',
            with: [
                'title'         => 'Node Access Provisioned',
                'type'          => 'created',
                'message_intro' => "Your dedicated tenant environment ({$this->tenant->name}) has been successfully provisioned on HIVE.OS. You can now access your management gateway using the credentials below.",
                'actionUrl'     => $actionUrl,
                'actionText'    => 'Login to Tenant Gateway',
                'rawPassword'   => $this->rawPassword,
                'appName'       => 'HIVE.OS',
                'logoUrl'       => $logoUrl,
                'user'          => $this->admin, // Pass the admin user to the template
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
