<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        $status = $this->user->is_active ? 'Activated' : 'Deactivated';

        return new Envelope(
            subject: "Hive Network Status: $status",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.universal',
            with: [
                'title' => 'Status Change',
                'type' => 'status',
                'message_intro' => 'Your account status on the Hive network has been updated.',
            ],
        );
    }
}
