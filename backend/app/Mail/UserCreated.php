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
        public string $actionUrl
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
        return new Content(
            view: 'emails.universal',
            with: [
                'title' => 'Activate Account',
                'type' => 'created',
                'message_intro' => 'Your account has been provisioned. To access the network, you must establish your secure credentials.',
            ],
        );
    }
}
