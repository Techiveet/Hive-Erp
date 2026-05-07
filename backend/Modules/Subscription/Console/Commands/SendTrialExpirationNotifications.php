<?php

namespace Modules\Subscription\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Subscription\Mail\TrialExpirationWarning;
use Modules\Subscription\Models\TenantSubscription;
use Modules\Tenancy\Models\Tenant;

class SendTrialExpirationNotifications extends Command
{
    protected $signature = 'subscription:trial-expiration-notify';
    protected $description = 'Send trial expiration notifications to tenants';

    public function handle(): int
    {
        $this->info('Checking for trial tenants with expiring subscriptions...');

        // Days before expiration to send notifications
        $notificationDays = [7, 3, 1, 0]; // 0 means expired

        $sentCount = 0;

        foreach ($notificationDays as $daysBeforeExpiration) {
            $sentCount += $this->sendNotificationsForDay($daysBeforeExpiration);
        }

        $this->info("Total notifications sent: {$sentCount}");

        return self::SUCCESS;
    }

    protected function sendNotificationsForDay(int $daysBeforeExpiration): int
    {
        $targetDate = now()->addDays($daysBeforeExpiration)->startOfDay();

        // Find trial subscriptions expiring on the target date
        $expiringSubscriptions = TenantSubscription::query()
            ->whereHas('tenant', function ($query) {
                $query->where('plan', 'larva');
            })
            ->whereDate('expires_at', $targetDate)
            ->with('tenant')
            ->get();

        $sent = 0;

        foreach ($expiringSubscriptions as $subscription) {
            $tenant = $subscription->tenant;

            if (!$tenant) {
                continue;
            }

            // Check if notification already sent for this day
            $notificationKey = "trial_notification_sent_{$daysBeforeExpiration}";
            $sentNotifications = $subscription->metadata['trial_notifications'] ?? [];

            if (in_array($notificationKey, $sentNotifications)) {
                continue; // Already sent
            }

            try {
                $adminEmail = $this->getAdminEmail($tenant);
                if (!$adminEmail) {
                    continue;
                }

                $daysRemaining = $daysBeforeExpiration;
                $renewUrl = $this->getRenewUrl($tenant);

                Mail::to($adminEmail)->send(new TrialExpirationWarning(
                    $tenant,
                    $daysRemaining,
                    $renewUrl
                ));

                // Mark notification as sent
                $sentNotifications[] = $notificationKey;
                $subscription->metadata = array_merge($subscription->metadata ?? [], [
                    'trial_notifications' => $sentNotifications,
                ]);
                $subscription->save();

                $sent++;
                $this->info("Sent notification to {$adminEmail} for tenant {$tenant->id} ({$daysRemaining} days remaining)");
            } catch (\Throwable $e) {
                Log::warning('Trial expiration notification failed: ' . $e->getMessage(), [
                    'tenant_id' => $tenant->id,
                    'exception' => $e,
                ]);
            }
        }

        return $sent;
    }

    protected function getAdminEmail(Tenant $tenant): ?string
    {
        try {
            $tenant->makeCurrent();
            $admin = \Modules\Identity\Models\User::query()
                ->orderBy('created_at')
                ->first();
            \Tenancy\Tenancy::centralize();

            return $admin?->email;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function getRenewUrl(Tenant $tenant): string
    {
        $domain = $tenant->domains()->first()?->domain ?? "{$tenant->id}.localhost";
        return "https://{$domain}/dashboard/subscriptions";
    }
}
