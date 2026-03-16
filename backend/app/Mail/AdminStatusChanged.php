<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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

        return new Content(
            view: 'emails.universal',
            with: [
                'title'         => 'Access ' . ucfirst($statusText),
                'type'          => 'notification',
                'user'          => $this->user,
                'message_intro' => "Your Super Admin access for the '{$this->tenantName}' node has been {$statusText}. {$instruction}",

                // 🚀 INJECT BUTTON DATA (Only show the button if they are active)
                'actionUrl'     => $this->isActive ? "http://{$tenantId}.localhost:3000/sign-in" : null,
                'actionText'    => 'Login to Tenant Gateway',

                'appName'       => 'HIVE.OS',
                'logoUrl'       => 'https://techiveet.com/frontend/images/resources/logo1.png',
            ],
        );
    }
}
