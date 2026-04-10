<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Identity\Database\Seeders\CentralRolesSeeder;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Tenancy\Database\Seeders\TenantFoundationSeeder;
use Modules\Tenancy\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class SyncSystemAccessCommand extends Command
{
    protected $signature = 'hive:sync-system-access {--force : Run without confirmation}';

    protected $description = 'Reseed central and tenant access control, then sync all Super Admin users to the full permission catalog.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will reseed system access and sync all Super Admin permissions. Continue?')) {
            $this->components->warn('System access sync cancelled.');

            return self::SUCCESS;
        }

        $this->components->info('Syncing central roles and permissions...');
        $this->call(CentralRolesSeeder::class);
        $this->syncCentralSuperAdmins();

        $tenants = Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->components->info('No tenants found. Central access sync is complete.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Syncing tenant access for %d tenant(s)...', $tenants->count()));

        foreach ($tenants as $tenant) {
            $this->line(" - {$tenant->id}");

            try {
                tenancy()->initialize($tenant);
                app()[PermissionRegistrar::class]->forgetCachedPermissions();

                $this->call(TenantFoundationSeeder::class);
                $this->syncTenantSuperAdmins();
            } catch (Throwable $exception) {
                $this->components->error("Failed while syncing tenant [{$tenant->id}]: {$exception->getMessage()}");

                return self::FAILURE;
            } finally {
                tenancy()->end();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->components->info('System access sync completed successfully.');

        return self::SUCCESS;
    }

    private function syncCentralSuperAdmins(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        User::query()
            ->with('roles')
            ->get()
            ->filter(fn (User $user) => $user->roles->contains(fn ($role) => $role->name === 'Super Admin' && $role->guard_name === 'web'))
            ->each(function (User $user) use ($permissions) {
                $user->syncPermissions($permissions);
            });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function syncTenantSuperAdmins(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = Permission::query()
            ->where('guard_name', 'tenant')
            ->pluck('name')
            ->all();

        User::query()
            ->with('roles')
            ->get()
            ->filter(fn (User $user) => $user->roles->contains(fn ($role) => $role->name === 'Super Admin' && $role->guard_name === 'tenant'))
            ->each(function (User $user) use ($permissions) {
                $user->syncPermissions($permissions);
            });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
