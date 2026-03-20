<?php

namespace Modules\Identity\Mail;

use Modules\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token,
        public string $rawPassword
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to HIVE.OS - Node Provisioned');
    }

    public function content(): Content
    {
        // 🚀 Detect the current tenant context
        $tenantId = function_exists('tenant') ? tenant('id') : null;
        $domain = $tenantId ? "http://{$tenantId}.localhost:3000" : "http://localhost:3000";

        return new Content(
           view: 'core::emails.universal',
            with: [
                'title'         => 'Node Access Provisioned',
                'type'          => 'created',
                'message_intro' => 'Your tenant database has been deployed. You can now access your management gateway using the credentials below.',

                // 🚀 INJECT BUTTON DATA
                'actionUrl'     => "{$domain}/sign-in",
                'actionText'    => 'Login to Tenant Gateway',

                'rawPassword'   => $this->rawPassword,
                'appName'       => 'HIVE.OS',
                'logoUrl'       => 'https://techiveet.com/frontend/images/resources/logo1.png',
            ],
        );
    }
}
