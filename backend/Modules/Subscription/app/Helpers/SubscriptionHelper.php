<?php

use Modules\Subscription\Support\FeatureAccessService;

if (!function_exists('hasModule')) {
    function hasModule(string $moduleSlug): bool
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return app(FeatureAccessService::class)->hasModule($tenant, $moduleSlug);
    }
}

if (!function_exists('hasSubmodule')) {
    function hasSubmodule(string $moduleSlug): bool
    {
        return hasModule($moduleSlug);
    }
}

if (!function_exists('hasFeature')) {
    function hasFeature(string $featureSlug): bool
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return app(FeatureAccessService::class)->hasFeature($tenant, $featureSlug);
    }
}

if (!function_exists('subscribedTo')) {
    function subscribedTo(string $moduleSlug): bool
    {
        return hasModule($moduleSlug);
    }
}
