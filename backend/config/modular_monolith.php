<?php

return [
    'app_shell_responsibilities' => [
        'Boot Laravel and infrastructure providers',
        'Register cross-module middleware and transport concerns',
        'Compose modules without owning business workflows',
    ],

    'modules' => [
        'core' => [
            'name' => 'Core',
            'backend_namespace' => 'Modules\\Core',
            'backend_paths' => [
                'Modules/Core/app',
                'Modules/Core/routes',
            ],
            'frontend_namespace' => '@/modules/core',
            'frontend_paths' => [
                'modules/core',
                'app/dashboard',
            ],
            'dependencies' => [],
            'responsibilities' => [
                'dashboard',
                'settings',
                'audit logs',
                'file manager',
                'system operations',
                'api documentation',
            ],
        ],
        'identity' => [
            'name' => 'Identity',
            'backend_namespace' => 'Modules\\Identity',
            'backend_paths' => [
                'Modules/Identity/app',
                'Modules/Identity/routes',
            ],
            'frontend_namespace' => '@/modules/identity',
            'frontend_paths' => [
                'modules/identity',
                'app/dashboard/security',
                'app/(auth)',
            ],
            'dependencies' => [
                'core',
            ],
            'responsibilities' => [
                'authentication',
                'profiles',
                'users',
                'roles',
                'permissions',
                'two-factor authentication',
            ],
        ],
        'tenancy' => [
            'name' => 'Tenancy',
            'backend_namespace' => 'Modules\\Tenancy',
            'backend_paths' => [
                'Modules/Tenancy/app',
                'Modules/Tenancy/routes',
            ],
            'frontend_namespace' => '@/modules/tenancy',
            'frontend_paths' => [
                'modules/tenancy',
                'app/dashboard/tenants',
            ],
            'dependencies' => [
                'core',
                'identity',
            ],
            'responsibilities' => [
                'tenant provisioning',
                'tenant lifecycle',
                'tenant administration',
                'tenant exports',
            ],
        ],
    ],
];
