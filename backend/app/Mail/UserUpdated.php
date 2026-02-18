<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public array $changes = []
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Hive Security: Profile Update Notification',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.universal',
            with: [
                'title' => 'Profile Updated',
                'type' => 'updated',
                'message_intro' => 'Security alert: Your account details have been modified.',
                'changes' => $this->changes
            ],
        );
    }
}
