<?php

namespace Modules\Subscription\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Subscription\Models\SubscriptionFeature;
use Modules\Subscription\Models\SubscriptionModule;

class SubscriptionFeatureFactory extends Factory
{
    protected $model = SubscriptionFeature::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(3);

        return [
            'subscription_module_id' => SubscriptionModule::factory(),
            'subscription_submodule_id' => null,
            'slug' => $slug,
            'name' => str($slug)->headline()->toString(),
            'feature_type' => 'route',
            'route_name' => null,
            'route_uri' => null,
            'http_methods' => ['GET'],
            'permission' => null,
            'module_gate' => null,
            'metadata' => [],
        ];
    }
}
