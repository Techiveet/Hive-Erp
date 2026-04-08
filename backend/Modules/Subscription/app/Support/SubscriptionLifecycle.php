<?php

namespace Modules\Subscription\Support;

use Carbon\CarbonInterface;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;

class SubscriptionLifecycle
{
    public static function termDays(?string $plan = null): int
    {
        return self::configInt('subscription.default_term_days', 30);
    }

    public static function gracePeriodDays(?string $plan = null): int
    {
        return self::configInt('subscription.grace_period_days', 7);
    }

    public static function renewalNoticeDays(?string $plan = null): int
    {
        return self::configInt('subscription.renewal_notice_days', 5);
    }

    public static function startWindow(?string $plan = null, CarbonInterface|string|null $anchor = null): array
    {
        $startedAt = $anchor instanceof CarbonInterface
            ? $anchor->copy()
            : Carbon::parse($anchor ?? now());

        $expiresAt = $startedAt->copy()->addDays(self::termDays($plan));

        return [
            'started_at' => $startedAt,
            'renewal_window_starts_at' => $expiresAt->copy()->subDays(self::renewalNoticeDays($plan)),
            'expires_at' => $expiresAt,
            'grace_ends_at' => $expiresAt->copy()->addDays(self::gracePeriodDays($plan)),
        ];
    }

    public static function renewWindow(?string $plan, CarbonInterface|string|null $currentExpiry = null, CarbonInterface|string|null $paidAt = null): array
    {
        $paidMoment = $paidAt instanceof CarbonInterface
            ? $paidAt->copy()
            : Carbon::parse($paidAt ?? now());

        $currentExpiryMoment = $currentExpiry
            ? ($currentExpiry instanceof CarbonInterface ? $currentExpiry->copy() : Carbon::parse($currentExpiry))
            : null;

        $nextStart = $currentExpiryMoment && $currentExpiryMoment->greaterThan($paidMoment)
            ? $currentExpiryMoment->copy()
            : $paidMoment;

        $expiresAt = $nextStart->copy()->addDays(self::termDays($plan));

        return [
            'last_renewed_at' => $paidMoment,
            ...self::windowForExpiry($plan, $expiresAt),
        ];
    }

    public static function windowForExpiry(?string $plan, CarbonInterface|string $expiresAt): array
    {
        $expiryMoment = $expiresAt instanceof CarbonInterface
            ? $expiresAt->copy()
            : Carbon::parse($expiresAt);

        return [
            'renewal_window_starts_at' => $expiryMoment->copy()->subDays(self::renewalNoticeDays($plan)),
            'expires_at' => $expiryMoment,
            'grace_ends_at' => $expiryMoment->copy()->addDays(self::gracePeriodDays($plan)),
        ];
    }

    public static function statusFor(?CarbonInterface $expiresAt, ?CarbonInterface $graceEndsAt, CarbonInterface|string|null $now = null): string
    {
        if (!$expiresAt) {
            return 'pending_activation';
        }

        $moment = $now instanceof CarbonInterface ? $now->copy() : Carbon::parse($now ?? now());

        if ($graceEndsAt && $moment->greaterThanOrEqualTo($graceEndsAt)) {
            return 'expired';
        }

        if ($moment->greaterThanOrEqualTo($expiresAt)) {
            return 'grace_period';
        }

        return 'active';
    }

    public static function summary(?CarbonInterface $expiresAt, ?CarbonInterface $graceEndsAt, CarbonInterface|string|null $now = null): array
    {
        if (!$expiresAt) {
            return [
                'days_until_expiration' => null,
                'is_expiring_soon' => false,
                'needs_renewal' => false,
                'status' => 'pending_activation',
            ];
        }

        $moment = $now instanceof CarbonInterface ? $now->copy() : Carbon::parse($now ?? now());
        $daysUntilExpiration = $moment->diffInDays($expiresAt, false);
        $status = self::statusFor($expiresAt, $graceEndsAt, $moment);

        return [
            'days_until_expiration' => $daysUntilExpiration,
            'is_expiring_soon' => $status === 'active' && $daysUntilExpiration <= self::renewalNoticeDays(),
            'needs_renewal' => in_array($status, ['grace_period', 'expired'], true) || $daysUntilExpiration <= self::renewalNoticeDays(),
            'status' => $status,
        ];
    }

    protected static function configInt(string $key, int $default): int
    {
        try {
            $container = Container::getInstance();

            if (!$container || !$container->bound('config')) {
                return $default;
            }

            return (int) $container->make('config')->get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
