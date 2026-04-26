<?php

namespace Modules\Tenancy\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Subscription\Support\TenantSubscriptionService;
use Modules\Tenancy\Database\Seeders\TenantFoundationSeeder;
use Modules\Tenancy\Mail\TenantCreated;
use Modules\Tenancy\Models\Tenant;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Throwable;

class TenantProvisioningService
{
    public function __construct(
        protected TenantSubscriptionService $subscriptions,
        protected TenantDomainService $domains,
        protected TenantLandingTemplateCatalog $landingTemplates,
    ) {
    }

    public function provision(array $payload, ?string $initiatedBy = null): Tenant
    {
        try {
            $tenant = Tenant::withoutEvents(function () use ($payload, $initiatedBy) {
                $tenant = new Tenant();
                $tenant->id = strtolower($payload['id']);
                $tenant->name = $payload['name'];
                $tenant->plan = $payload['plan'];
                $tenant->is_active = true;
                $tenant->admin_email = strtolower($payload['admin_email']);
                $tenant->business_type = $this->landingTemplates->normalizeBusinessType($payload['business_type'] ?? null);
                $tenant->landing_page_template = $this->landingTemplates->normalizeTemplate(
                    $payload['landing_page_template'] ?? null,
                    $payload['business_type'] ?? null
                );
                $tenant->save();

                return $tenant;
            });

            $this->domains->createFallbackDomain($tenant, $payload['domain']);

            $dbManager = $tenant->database()->manager();
            $dbName = $tenant->database()->getName();

            if (!$dbManager->databaseExists($dbName)) {
                dispatch_sync(new CreateDatabase($tenant));
            }

            Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->id],
                '--path' => [
                    'database/migrations/tenant',
                    'Modules/Identity/database/migrations/tenant',
                    'Modules/Core/database/migrations/tenant',
                    'Modules/Chat/database/migrations/tenant',
                    'Modules/MailBox/database/migrations/tenant',
                    'Modules/Inventory/database/migrations',
                    'Modules/NightClub/database/migrations',
                ],
            ]);

            Artisan::call('tenants:seed', [
                '--tenants' => [$tenant->id],
                '--class' => TenantFoundationSeeder::class,
                '--force' => true,
            ]);

            $tenant->run(function () use ($payload, $tenant) {
                app()[PermissionRegistrar::class]->forgetCachedPermissions();

                $admin = User::updateOrCreate(
                    ['email' => strtolower($payload['admin_email'])],
                    [
                        'name' => $payload['admin_name'],
                        'password' => Hash::make($payload['admin_password']),
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]
                );

                $admin->guard_name = 'tenant';

                $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'tenant']);
                $admin->syncRoles([$role->name]);
                $admin->syncPermissions(
                    Permission::where('guard_name', 'tenant')->pluck('name')->all()
                );

                app()[PermissionRegistrar::class]->forgetCachedPermissions();

                try {
                    $token = \Illuminate\Support\Facades\Password::broker()->createToken($admin);
                    Mail::to($admin->email)->send(new TenantCreated($tenant, $admin, $payload['admin_password'], $token));
                } catch (Throwable $mailException) {
                    \Illuminate\Support\Facades\Log::warning('Tenant welcome email failed: ' . $mailException->getMessage());
                }
            });

            $this->subscriptions->ensureForTenant(
                $tenant,
                $payload['module_subscriptions'] ?? null,
                $initiatedBy ?? strtolower($payload['admin_email']),
                true
            );

            return $tenant->load('domains');
        } catch (Throwable $exception) {
            if (isset($tenant) && $tenant instanceof Tenant && $tenant->exists) {
                $dbName = 'tenant' . $tenant->id;

                try {
                    DB::statement('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ?', [$dbName]);
                    DB::statement("DROP DATABASE IF EXISTS \"$dbName\"");
                } catch (Throwable $dropException) {
                    // Best effort cleanup only.
                }

                $tenant->deleteQuietly();
            }

            throw $exception;
        }
    }
}
