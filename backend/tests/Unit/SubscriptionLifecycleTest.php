<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Modules\Subscription\Support\SubscriptionLifecycle;
use PHPUnit\Framework\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    public function test_start_window_uses_default_term_and_grace_period(): void
    {
        $window = SubscriptionLifecycle::startWindow('business', Carbon::parse('2026-04-08 00:00:00'));

        $this->assertSame('2026-05-08 00:00:00', $window['expires_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-15 00:00:00', $window['grace_ends_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-03 00:00:00', $window['renewal_window_starts_at']->format('Y-m-d H:i:s'));
    }

    public function test_renew_window_extends_from_existing_expiry_when_subscription_is_still_active(): void
    {
        $window = SubscriptionLifecycle::renewWindow(
            'business',
            Carbon::parse('2026-05-08 00:00:00'),
            Carbon::parse('2026-04-20 00:00:00'),
        );

        $this->assertSame('2026-06-07 00:00:00', $window['expires_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-14 00:00:00', $window['grace_ends_at']->format('Y-m-d H:i:s'));
    }

    public function test_summary_marks_expired_subscriptions_as_needing_renewal(): void
    {
        $summary = SubscriptionLifecycle::summary(
            Carbon::parse('2026-04-01 00:00:00'),
            Carbon::parse('2026-04-07 00:00:00'),
            Carbon::parse('2026-04-08 12:00:00'),
        );

        $this->assertSame('expired', $summary['status']);
        $this->assertTrue($summary['needs_renewal']);
        $this->assertFalse($summary['is_expiring_soon']);
    }
}
