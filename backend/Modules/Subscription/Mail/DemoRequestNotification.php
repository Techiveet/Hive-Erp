<?php

namespace Modules\Subscription\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Modules\Subscription\Models\DemoRequest;

class DemoRequestNotification extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DemoRequest $demoRequest,
    ) {
    }

    public function build(): self
    {
        $interests = is_array($this->demoRequest->interests) 
            ? implode(', ', $this->demoRequest->interests) 
            : 'Not specified';

        return $this->subject('New Demo Request: ' . $this->demoRequest->company)
            ->view('subscription::emails.demo-request')
            ->with([
                'demoRequest' => $this->demoRequest,
                'interests' => $interests,
                'adminPanelUrl' => config('app.url') . '/dashboard/subscriptions', // Adjust as needed
            ]);
    }
}
