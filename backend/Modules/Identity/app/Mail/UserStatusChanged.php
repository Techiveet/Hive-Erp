<?php

namespace Modules\Identity\Mail;

use Modules\Identity\Models\User;
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
            subject: "Hive Security: Network Access $status",
        );
    }

    public function content(): Content
    {
        $actionUrl = null;
        $actionText = null;

        if ($this->user->is_active) {
            $title = 'Access Restored';
            $messageIntro = 'Security alert: Your system identity has been ACTIVATED. You now have full access to the network and may authenticate.';

            // 🚀 Dynamically generate the login URL for tenants or central
            $domain = function_exists('tenant') && tenant('id')
                ? 'http://' . tenant('id') . '.localhost:3000'
                : env('FRONTEND_URL', 'http://localhost:3000');

            $actionUrl = "{$domain}/sign-in";
            $actionText = 'AUTHENTICATE NOW';
        } else {
            $title = 'Access Suspended';
            $messageIntro = 'Security alert: Your system identity has been DEACTIVATED. Your access to the network has been temporarily suspended.';
        }

        return new Content(
            view: 'core::emails.universal',
            with: [
                'title'         => $title,
                'type'          => 'status',
                'message_intro' => $messageIntro,
                // 🚀 Pass the button data (will be null if deactivated)
                'actionUrl'     => $actionUrl,
                'actionText'    => $actionText,
            ],
        );
    }
}
