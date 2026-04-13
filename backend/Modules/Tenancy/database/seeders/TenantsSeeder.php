<?php

namespace Modules\Tenancy\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\TenantUsersSeeder;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantLandingTemplateCatalog;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class TenantsSeeder extends Seeder
{
    public function run(): void
    {
        $rootDomain = strtolower(trim((string) env('ROOT_DOMAIN', '')));
        $landingTemplates = app(TenantLandingTemplateCatalog::class);
        $defaultTenantDomain = static fn (string $tenantId): string => $rootDomain !== ''
            ? "{$tenantId}.{$rootDomain}"
            : "{$tenantId}.localhost";

        $tenants = [
            [
                'id' => 'apple',
                'name' => 'Apple Inc',
                'plan' => 'overlord',
                'business_type' => 'retail',
                'admin_email' => 'admin@apple.com',
            ],
            [
                'id' => 'tesla',
                'name' => 'Tesla Motors',
                'plan' => 'business',
                'business_type' => 'general',
                'admin_email' => 'admin@tesla.com',
            ],
            [
                'id' => 'selam-bistro',
                'name' => 'Selam Bistro',
                'plan' => 'business',
                'business_type' => 'restaurant',
                'admin_email' => 'admin@selam-bistro.com',
            ],
            [
                'id' => 'nile-suites',
                'name' => 'Nile Suites',
                'plan' => 'enterprise',
                'business_type' => 'hotel',
                'admin_email' => 'admin@nile-suites.com',
            ],
            [
                'id' => 'afya-clinic',
                'name' => 'Afya Clinic',
                'plan' => 'enterprise',
                'business_type' => 'clinic',
                'admin_email' => 'admin@afya-clinic.com',
            ],
        ];

        foreach ($tenants as $data) {
            $this->command?->info('Spawning Node: ' . $data['name']);
            $businessType = $landingTemplates->normalizeBusinessType($data['business_type'] ?? null);

            $tenant = Tenant::withoutEvents(function () use ($data, $businessType, $landingTemplates) {
                return Tenant::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'name' => $data['name'],
                        'plan' => $data['plan'],
                        'is_active' => true,
                        'admin_email' => $data['admin_email'],
                        'admin_active' => true,
                        'business_type' => $businessType,
                        'landing_page_template' => $landingTemplates->defaultTemplate($businessType),
                    ]
                );
            });

            $tenant->domains()->updateOrCreate([
                'domain' => $defaultTenantDomain($data['id']),
            ], [
                'is_primary' => true,
                'is_fallback' => true,
                'verification_status' => 'verified',
                'verification_token' => null,
                'verified_at' => now(),
            ]);

            $dbManager = $tenant->database()->manager();
            $dbName = $tenant->database()->getName();

            if (! $dbManager->databaseExists($dbName)) {
                $this->command?->info("   -> Allocating new database: {$dbName}");
                dispatch_sync(new CreateDatabase($tenant));
                dispatch_sync(new MigrateDatabase($tenant));
            } else {
                $this->command?->warn("   -> Database {$dbName} already exists. Re-linking node.");
                dispatch_sync(new MigrateDatabase($tenant));
            }

            $tenant->run(function () {
                app()[PermissionRegistrar::class]->forgetCachedPermissions();

                $this->call(TenantFoundationSeeder::class);
                $this->call(TenantUsersSeeder::class);

                app()[PermissionRegistrar::class]->forgetCachedPermissions();
            });

            $this->command?->info('Node ' . $data['id'] . ' is online.');
        }
    }
}
