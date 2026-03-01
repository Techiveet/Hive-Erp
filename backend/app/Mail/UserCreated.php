<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // ⚡ Essential for Octane speed
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public string $token,
        public string $rawPassword
    ) {}

    /**
     * Get the message envelope (Headers & Subject).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Hive ERP - Activate Your Account',
        );
    }

    /**
     * Get the message content definition (View & Data).
     */
    public function content(): Content
{
    // Determine the frontend URL (Central vs Tenant)
    $domain = function_exists('tenant') && tenant('id') 
        ? 'http://' . tenant('id') . '.localhost:3000' 
        : 'http://localhost:3000';

    $actionUrl = "{$domain}/reset-password?token={$this->token}&email=" . urlencode($this->user->email);

    return new Content(
        view: 'emails.universal',
        with: [
            'title'         => 'Activate Account',
            'type'          => 'created',
            'message_intro' => 'Your account has been provisioned. To access the network, you must establish your secure credentials.',
            'actionUrl'     => $actionUrl,
            'rawPassword'   => $this->rawPassword,
            
            // 🚀 Dynamic Branding Variables
            'appName'       => 'HIVE.OS',
            // Provide a public URL to your logo here. If left null or removed, it defaults to the text logo.
            // 'logoUrl'       => 'https://ui-avatars.com/api/?name=HIVE&color=ffffff&background=ea580c&rounded=true', 
            'logoUrl'       => 'https://techiveet.com/frontend/images/resources/logo1.png', 
        ],
    );
}
}