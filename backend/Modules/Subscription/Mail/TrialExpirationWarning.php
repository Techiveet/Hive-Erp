<?php

namespace Modules\Subscription\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Modules\Tenancy\Models\Tenant;

class TrialExpirationWarning extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
        public int $daysRemaining,
        public string $renewUrl,
    ) {
    }

    public function build(): self
    {
        $planName = ucfirst($this->tenant->plan ?? 'larva');
        $isExpired = $this->daysRemaining <= 0;
        $subject = $isExpired
            ? "Your {$planName} Trial Has Expired"
            : "Your {$planName} Trial Expires in {$this->daysRemaining} Days";

        return $this->subject($subject)
            ->view('subscription::emails.trial-expiration')
            ->with([
                'tenant' => $this->tenant,
                'daysRemaining' => $this->daysRemaining,
                'isExpired' => $isExpired,
                'renewUrl' => $this->renewUrl,
                'tenantName' => $this->tenant->name ?? ucfirst($this->tenant->id),
            ]);
    }
}
