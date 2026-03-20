<?php

namespace Modules\Tenancy\Mail;

use Modules\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
        $domain = $tenantId ? "http://{$tenantId}.localhost:3000" : "http://localhost:3000";

        return new Content(
            view: 'core::emails.universal',
            with: [
                'title'         => 'Credentials Updated',
                'type'          => 'updated',
                'message_intro' => 'Your Super Admin access credentials for the tenant node have been modified by Central Command.',
                'changes'       => $this->changes,

                // 🚀 INJECT BUTTON DATA
                'actionUrl'     => "{$domain}/sign-in",
                'actionText'    => 'Login with New Credentials',

                'appName'       => 'HIVE.OS',
                'logoUrl'       => 'https://techiveet.com/frontend/images/resources/logo1.png',
            ],
        );
    }
}
