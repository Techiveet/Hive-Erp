<?php

namespace Modules\Identity\Mail;

use Modules\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\Setting; // 🚀 Added Settings Model

class UserUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $formattedChanges = [];

    public function __construct(
        public User $user,
        public array $changes = []
    ) {
        // Clean up raw database fields into a human-readable format
        foreach ($changes as $key => $value) {
            // 1. Skip fields we don't want to show the user
            if (in_array($key, ['updated_at', 'password', 'remember_token', 'tenant_id'])) {
                continue;
            }

            // 2. Special case: The password status flag from the UserController
            if ($key === 'password_status') {
                $this->formattedChanges['Encryption Key'] = '[ REDACTED FOR SECURITY ] - Successfully Updated';
                continue;
            }

            // 3. Format the Key (e.g., 'is_active' -> 'Is Active')
            $formattedKey = ucwords(str_replace('_', ' ', $key));

            // 4. Handle booleans/status formatting
            if ($key === 'is_active' || is_bool($value)) {
                $value = $value ? 'Active' : 'Locked';
            }

            $this->formattedChanges[$formattedKey] = $value;
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Hive Security: Profile Update Notification',
        );
    }

    public function content(): Content
    {
        // 🚀 Fetch the dynamic logo
        $logoUrl = $this->resolveBrandLogoUrl();

        return new Content(
            view: 'core::emails.universal',
            with: [
                'title'         => 'Profile Updated',
                'type'          => 'updated',
                'message_intro' => 'Security alert: Your system identity attributes have been modified. See the ledger below for details.',
                'changes'       => $this->formattedChanges,
                'logoUrl'       => $logoUrl, // 🚀 Passed to the view
            ],
        );
    }

    // 🚀 Added the resolver method
    protected function resolveBrandLogoUrl(): string
    {
        $fallback = 'https://techiveet.com/frontend/images/resources/logo1.png';

        $logoPath = cache()->remember(
            'mail_brand_logo_dark_path',
            now()->addHour(),
            fn () => Setting::where('key', 'logo_dark')->value('value')
        );

        if (empty($logoPath)) {
            return $fallback;
        }

        if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
            return $logoPath;
        }

        return asset(ltrim($logoPath, '/'));
    }
}
