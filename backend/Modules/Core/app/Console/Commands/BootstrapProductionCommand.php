<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Identity\Database\Seeders\CentralRolesSeeder;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Spatie\Permission\PermissionRegistrar;

class BootstrapProductionCommand extends Command
{
    protected $signature = 'hive:bootstrap-production
        {--name=System Administrator : The display name for the central super admin}
        {--email= : The email address for the central super admin}
        {--password= : The password for the central super admin}
        {--force : Run without the safety confirmation}';

    protected $description = 'Bootstrap a production-safe central Hive installation without demo tenants or test users.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will seed central production data and create or update the central super admin. Continue?')) {
            $this->components->warn('Bootstrap cancelled.');

            return self::SUCCESS;
        }

        $email = (string) ($this->option('email') ?: env('PROD_ADMIN_EMAIL', ''));

        if ($email === '') {
            $this->components->error('Provide --email or set PROD_ADMIN_EMAIL before running this command.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: env('PROD_ADMIN_NAME', 'System Administrator'));
        $password = (string) ($this->option('password') ?: env('PROD_ADMIN_PASSWORD', ''));

        $this->components->info('Seeding central roles and core settings...');
        $this->call(CentralRolesSeeder::class);
        $this->call(CoreDatabaseSeeder::class);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::query()->where('email', $email)->first();
        $passwordWasGenerated = false;

        if (! $user && $password === '') {
            $password = bin2hex(random_bytes(12));
            $passwordWasGenerated = true;
        }

        $attributes = [
            'name' => $name,
            'email_verified_at' => now(),
            'is_active' => true,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ];

        if ($password !== '') {
            $attributes['password'] = Hash::make($password);
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            $attributes
        );

        $user->syncRoles(['Super Admin']);
        $user->syncPermissions(
            Permission::where('guard_name', 'web')->pluck('name')->all()
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->newLine();
        $this->components->info('Production bootstrap completed.');
        $this->line("Admin email: {$email}");

        if ($passwordWasGenerated) {
            $this->line("Generated password: {$password}");
        } elseif ($password !== '') {
            $this->line('Password: updated from the provided option/env value');
        } else {
            $this->line('Password: unchanged for the existing user');
        }

        return self::SUCCESS;
    }
}
