<?php

namespace Modules\Chat\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ChatServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Chat';

    protected string $nameLower = 'chat';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}