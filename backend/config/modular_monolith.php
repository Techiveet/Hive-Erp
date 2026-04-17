<?php

return [
    'app_shell_responsibilities' => [
        'Boot Laravel and infrastructure providers',
        'Register cross-module middleware and transport concerns',
        'Compose modules without owning business workflows',
    ],

    'interaction_strategy' => [
        'registry_mode' => 'static',
        'default_transport' => 'contracts_and_domain_events',
        'rules' => [
            'Declare dependencies in this registry before referencing another module.',
            'Prefer contracts, support services, and events over direct concrete imports.',
            'Treat routes, controllers, jobs, and database models as private unless explicitly listed in public_api.',
            'Do not dynamically load modules from the filesystem at runtime in production.',
        ],
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
            'public_api' => [
                'http_prefixes' => ['/api/v1/dashboard', '/api/v1/settings', '/api/v1/files'],
                'events' => [
                    'Modules\\Core\\Events\\DashboardActivityLogged',
                ],
                'contracts' => [
                    'Modules\\Core\\Support\\ModuleCatalog',
                    'Modules\\Core\\Support\\ModuleRegistry',
                    'Modules\\Core\\Support\\ResolvesExportBranding',
                ],
            ],
            'internal_namespaces' => [
                'Modules\\Core\\Http\\Controllers\\Settings',
                'Modules\\Core\\Jobs',
            ],
            'frontend_contract' => [
                'module_id' => 'core',
                'version' => '2026-04',
                'route_prefixes' => ['/dashboard'],
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
                'subscription',
            ],
            'responsibilities' => [
                'authentication',
                'profiles',
                'users',
                'roles',
                'permissions',
                'two-factor authentication',
            ],
            'public_api' => [
                'http_prefixes' => ['/api/v1/login', '/api/v1/logout', '/api/v1/security'],
                'events' => [],
                'contracts' => [
                    'App\\Support\\AuthContext',
                ],
            ],
            'internal_namespaces' => [
                'Modules\\Identity\\Mail',
                'Modules\\Identity\\Http\\Controllers\\Export',
            ],
            'frontend_contract' => [
                'module_id' => 'identity',
                'version' => '2026-04',
                'route_prefixes' => ['/sign-in', '/reset-password', '/dashboard/security'],
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
                'subscription',
            ],
            'responsibilities' => [
                'tenant provisioning',
                'tenant lifecycle',
                'tenant administration',
                'tenant exports',
            ],
            'public_api' => [
                'http_prefixes' => ['/api/v1/tenants', '/api/v1/tenant'],
                'events' => [],
                'contracts' => [
                    'Modules\\Tenancy\\Support\\TenantDomainService',
                    'Modules\\Tenancy\\Support\\TenantLandingTemplateCatalog',
                ],
            ],
            'internal_namespaces' => [
                'Modules\\Tenancy\\Mail',
                'Modules\\Tenancy\\Database\\Seeders',
            ],
            'frontend_contract' => [
                'module_id' => 'tenancy',
                'version' => '2026-04',
                'route_prefixes' => ['/dashboard/tenants'],
            ],
        ],
        'subscription' => [
            'name' => 'Subscription',
            'backend_namespace' => 'Modules\\Subscription',
            'backend_paths' => [
                'Modules/Subscription/app',
                'Modules/Subscription/routes',
            ],
            'frontend_namespace' => '@/modules/subscription',
            'frontend_paths' => [
                'modules/subscription',
            ],
            'dependencies' => [
                'core',
                'tenancy',
            ],
            'responsibilities' => [
                'tenant plans',
                'module enablement',
                'payment gateway orchestration',
                'subscription lifecycle',
            ],
            'public_api' => [
                'http_prefixes' => ['/api/v1/subscriptions', '/api/v1/tenant/subscriptions'],
                'events' => [],
                'contracts' => [
                    'Modules\\Subscription\\Contracts\\PaymentProvider',
                    'Modules\\Subscription\\Support\\TenantModuleCatalog',
                    'Modules\\Subscription\\Support\\TenantSubscriptionService',
                ],
            ],
            'internal_namespaces' => [
                'Modules\\Subscription\\Payments',
                'Modules\\Subscription\\Mail',
            ],
            'frontend_contract' => [
                'module_id' => 'subscription',
                'version' => '2026-04',
                'route_prefixes' => ['/dashboard/subscriptions'],
            ],
        ],
        'inventory' => [
            'name' => 'Inventory',
            'backend_namespace' => 'Modules\\Inventory',
            'backend_paths' => [
                'Modules/Inventory/app',
                'Modules/Inventory/routes',
            ],
            'frontend_namespace' => '@/modules/inventory',
            'frontend_paths' => [
                'modules/inventory',
                'app/dashboard/inventory',
            ],
            'dependencies' => [
                'core',
                'subscription',
            ],
            'responsibilities' => [
                'items',
                'documents',
                'stock ledger',
                'inventory reports',
                'inventory exports',
            ],
            'public_api' => [
                'http_prefixes' => ['/api/v1/inventory'],
                'events' => [],
                'contracts' => [
                    'Modules\\Inventory\\Support\\InventoryEntityCatalog',
                    'Modules\\Inventory\\Support\\InventoryWorkflowAliasCatalog',
                    'Modules\\Inventory\\Contracts\\InventoryIntegrationGateway',
                ],
            ],
            'internal_namespaces' => [
                'Modules\\Inventory\\Models',
                'Modules\\Inventory\\Exports',
            ],
            'frontend_contract' => [
                'module_id' => 'inventory',
                'version' => '2026-04',
                'route_prefixes' => ['/dashboard/inventory'],
            ],
        ],
        'warehouse' => [
            'name' => 'Warehouse',
            'backend_namespace' => 'Modules\\Warehouse',
            'backend_paths' => [
                'Modules/Warehouse/app',
                'Modules/Warehouse/routes',
            ],
            'frontend_namespace' => '@/modules/warehouse',
            'frontend_paths' => [
                'modules/warehouse',
                'app/dashboard/warehouse',
            ],
            'dependencies' => [
                'inventory',
            ],
            'responsibilities' => [
                'warehouses',
                'locations',
                'stock views',
                'stock movements',
            ],
            'public_api' => [
                'http_prefixes' => ['/api/v1/warehouse'],
                'events' => [],
                'contracts' => [
                    'Modules\\Warehouse\\Support\\WarehouseTenantContext',
                ],
            ],
            'internal_namespaces' => [
                'Modules\\Warehouse\\Models',
            ],
            'frontend_contract' => [
                'module_id' => 'warehouse',
                'version' => '2026-04',
                'route_prefixes' => ['/dashboard/warehouse'],
            ],
        ],
        'nightclub' => [
            'name' => 'NightClub',
            'backend_namespace' => 'Modules\\NightClub',
            'backend_paths' => [
                'Modules/NightClub/app',
                'Modules/NightClub/routes',
            ],
            'frontend_namespace' => '@/modules/nightclub',
            'frontend_paths' => [
                'modules/nightclub',
                'app/dashboard/nightclub',
            ],
            'dependencies' => [
                'inventory',
                'subscription',
            ],
            'responsibilities' => [
                'tables',
                'reservations',
                'service orders',
            ],
            'public_api' => [
                'http_prefixes' => ['/api/v1/nightclub', '/api/v1/public/nightclub'],
                'events' => [],
                'contracts' => [
                    'Modules\\Inventory\\Contracts\\InventoryIntegrationGateway',
                ],
            ],
            'internal_namespaces' => [
                'Modules\\NightClub\\Models',
            ],
            'frontend_contract' => [
                'module_id' => 'nightclub',
                'version' => '2026-04',
                'route_prefixes' => ['/dashboard/nightclub'],
            ],
        ],
        'mailbox' => [
            'name' => 'MailBox',
            'backend_namespace' => 'Modules\\MailBox',
            'backend_paths' => [
                'Modules/MailBox/app',
                'Modules/MailBox/routes',
            ],
            'frontend_namespace' => '@/components/mail',
            'frontend_paths' => [
                'components/mail',
                'app/dashboard/mail',
            ],
            'dependencies' => [
                'subscription',
                'identity',
            ],
            'responsibilities' => [
                'tenant mailbox',
                'realtime mail delivery',
                'mail quota tracking',
            ],
            'public_api' => [
                'http_prefixes' => ['/api/v1/mail'],
                'events' => [
                    'Modules\\MailBox\\Events\\MailReceived',
                ],
                'contracts' => [
                    'Modules\\MailBox\\Services\\MailboxStorageTracker',
                ],
            ],
            'internal_namespaces' => [
                'Modules\\MailBox\\Jobs',
                'Modules\\MailBox\\Models',
            ],
            'frontend_contract' => [
                'module_id' => 'mailbox',
                'version' => '2026-04',
                'route_prefixes' => ['/dashboard/mail'],
            ],
        ],
    ],
];
