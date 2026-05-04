<?php

namespace Modules\Subscription\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Subscription\Models\SubscriptionModule;

class SubscriptionModuleFactory extends Factory
{
    protected $model = SubscriptionModule::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'slug' => $slug,
            'name' => str($slug)->headline()->toString(),
            'description' => $this->faker->sentence(),
            'category' => 'Test',
            'tone' => 'slate',
            'backend_module' => null,
            'frontend_module' => null,
            'route_prefixes' => [],
            'metadata' => [],
        ];
    }
}
