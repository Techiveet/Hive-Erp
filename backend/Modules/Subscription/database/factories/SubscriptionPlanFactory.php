<?php

namespace Modules\Subscription\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Subscription\Models\SubscriptionPlan;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'slug' => $slug,
            'name' => str($slug)->headline()->toString(),
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'monthly_price_etb' => $this->faker->numberBetween(0, 10000),
            'mail_storage_quota_mb' => $this->faker->randomElement([512, 2048, 10240]),
            'trial_days' => 0,
            'sort_order' => 0,
            'metadata' => [],
        ];
    }
}
