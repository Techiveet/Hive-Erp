<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tenantName,
        public bool $isActive,
        public string $tenantId // 🚀 ADDED THIS
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

        return new Content(
            view: 'emails.universal',
            with: [
                'title'         => 'Workspace ' . ucfirst($statusText),
                'type'          => 'notification',
                'message_intro' => "The workspace '{$this->tenantName}' has been {$statusText} by the Central Command. {$instruction}",
                'user'          => (object) ['name' => 'Node Administrator'],

                // 🚀 INJECT BUTTON DATA (Only show button if they are active)
                'actionUrl'     => $this->isActive ? "http://{$this->tenantId}.localhost:3000/sign-in" : null,
                'actionText'    => 'Access Node Gateway',

                'appName'       => 'HIVE.OS',
                'logoUrl'       => 'https://techiveet.com/frontend/images/resources/logo1.png',
            ],
        );
    }
}
