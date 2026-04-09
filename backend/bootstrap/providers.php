<?php

return array_values(array_filter([
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)
        ? App\Providers\TelescopeServiceProvider::class
        : null,
    App\Providers\TenancyServiceProvider::class,
]));
