<?php

namespace Modules\Subscription\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Subscription\Mail\TenantSubscriptionRenewalReminder;
use Modules\Subscription\Models\TenantSubscription;
use Modules\Subscription\Support\SubscriptionLifecycle;
use Modules\Subscription\Support\TenantSubscriptionService;
use Modules\Tenancy\Models\Tenant;
use Throwable;

class ReconcileTenantSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:reconcile {--skip-reminders : Refresh states without sending renewal emails}';

    protected $description = 'Refresh tenant subscription status, renewal windows, and reminder notifications.';

    public function handle(TenantSubscriptionService $subscriptions): int
    {
        $processed = 0;
        $expiringSoon = 0;
        $expired = 0;
        $remindersSent = 0;

        TenantSubscription::query()->orderBy('tenant_id')->chunk(100, function ($records) use ($subscriptions, &$processed, &$expiringSoon, &$expired, &$remindersSent): void {
            foreach ($records as $record) {
                $record = $subscriptions->refreshState($record);
                $summary = SubscriptionLifecycle::summary($record->expires_at, $record->grace_ends_at);

                if ($summary['is_expiring_soon']) {
                    $expiringSoon++;
                }

                if ($summary['status'] === 'expired') {
                    $expired++;
                }

                if (!$this->option('skip-reminders')) {
                    $remindersSent += $this->dispatchReminderIfNeeded($record, $summary);
                }

                $processed++;
            }
        });

        $this->info("Processed {$processed} subscriptions. Expiring soon: {$expiringSoon}. Expired: {$expired}. Reminders sent: {$remindersSent}.");

        return self::SUCCESS;
    }

    protected function dispatchReminderIfNeeded(TenantSubscription $subscription, array $summary): int
    {
        [$stage, $column] = match ($summary['status']) {
            'expired' => ['expired', 'expired_notice_sent_at'],
            'grace_period' => ['grace_period', 'grace_reminder_sent_at'],
            default => $summary['is_expiring_soon']
                ? ['renewal_window', 'renewal_reminder_sent_at']
                : [null, null],
        };

        if (!$stage || !$column || $subscription->{$column}) {
            return 0;
        }

        $tenant = Tenant::query()->with('domains')->find($subscription->tenant_id);

        if (!$tenant || empty($tenant->admin_email)) {
            return 0;
        }

        try {
            Mail::to($tenant->admin_email)->queue(
                new TenantSubscriptionRenewalReminder($tenant, $subscription, $stage)
            );

            $subscription->{$column} = now();
            $subscription->save();

            return 1;
        } catch (Throwable $exception) {
            Log::warning('Tenant subscription reminder failed: ' . $exception->getMessage(), [
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'stage' => $stage,
            ]);

            return 0;
        }
    }
}
