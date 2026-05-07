<?php

namespace Modules\Subscription\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Tenancy\Models\Tenant;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = tenant();
        
        if (!$tenant) {
            $this->command?->error('No tenant context found. Demo data seeder must run within tenant context.');
            return;
        }

        $this->command?->info('Seeding demo data for trial tenant: ' . $tenant->id);
        
        $this->seedDemoUsers();
        $this->seedDemoInventory();
        $this->seedDemoHospitality();
        $this->seedDemoProjects();
        $this->seedDemoDocuments();
        
        $this->command?->info('Demo data seeded successfully!');
    }

    protected function seedDemoUsers(): void
    {
        if (!class_exists(\Modules\Identity\Models\User::class)) {
            return;
        }

        $users = [
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Abebe Kebede',
                'email' => 'demo.manager@trial.hive',
                'password' => bcrypt('demo1234'),
                'role' => 'manager',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Sara Ahmed',
                'email' => 'demo.staff@trial.hive',
                'password' => bcrypt('demo1234'),
                'role' => 'staff',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($users as $user) {
            try {
                DB::table('users')->insertOrIgnore($user);
            } catch (\Exception $e) {
                // Table might not exist or other error
            }
        }
    }

    protected function seedDemoInventory(): void
    {
        if (!class_exists(\Modules\Inventory\Models\Product::class)) {
            return;
        }

        $products = [
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Demo Coffee - Ethiopian Blend',
                'sku' => 'DEMO-001',
                'description' => 'Sample premium coffee product for demo purposes',
                'price' => 250.00,
                'quantity' => 100,
                'category' => 'Beverages',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Demo Teff Flour - Super Grade',
                'sku' => 'DEMO-002',
                'description' => 'Sample teff flour product for demo purposes',
                'price' => 120.00,
                'quantity' => 200,
                'category' => 'Grains',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Demo Honey - White Honey',
                'sku' => 'DEMO-003',
                'description' => 'Sample pure honey product for demo purposes',
                'price' => 350.00,
                'quantity' => 50,
                'category' => 'Natural Products',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($products as $product) {
            try {
                DB::table('products')->insertOrIgnore($product);
            } catch (\Exception $e) {
                // Table might not exist
            }
        }
    }

    protected function seedDemoHospitality(): void
    {
        if (!class_exists(\Modules\Hospitality\Models\Table::class)) {
            return;
        }

        $tables = [
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Table 1',
                'number' => 1,
                'capacity' => 4,
                'status' => 'available',
                'location' => 'Main Hall',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Table 2',
                'number' => 2,
                'capacity' => 2,
                'status' => 'available',
                'location' => 'Main Hall',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'VIP Table',
                'number' => 3,
                'capacity' => 8,
                'status' => 'available',
                'location' => 'VIP Lounge',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($tables as $table) {
            try {
                DB::table('tables')->insertOrIgnore($table);
            } catch (\Exception $e) {
                // Table might not exist
            }
        }

        // Add sample menu items
        if (class_exists(\Modules\Hospitality\Models\MenuItem::class)) {
            $menuItems = [
                [
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'name' => 'Demo Ethiopian Coffee',
                    'description' => 'Sample premium coffee',
                    'price' => 50.00,
                    'category' => 'Beverages',
                    'is_available' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'name' => 'Demo Injera Platter',
                    'description' => 'Sample traditional platter',
                    'price' => 150.00,
                    'category' => 'Main Course',
                    'is_available' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];

            foreach ($menuItems as $item) {
                try {
                    DB::table('menu_items')->insertOrIgnore($item);
                } catch (\Exception $e) {
                    // Table might not exist
                }
            }
        }
    }

    protected function seedDemoProjects(): void
    {
        if (!class_exists(\Modules\ProjectManagement\Models\Project::class)) {
            return;
        }

        $projects = [
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Demo Website Redesign',
                'description' => 'Sample project for testing project management features',
                'status' => 'in_progress',
                'priority' => 'high',
                'start_date' => now(),
                'end_date' => now()->addMonths(2),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Demo Mobile App Development',
                'description' => 'Sample mobile app project',
                'status' => 'planning',
                'priority' => 'medium',
                'start_date' => now()->addWeek(),
                'end_date' => now()->addMonths(4),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($projects as $project) {
            try {
                DB::table('projects')->insertOrIgnore($project);
            } catch (\Exception $e) {
                // Table might not exist
            }
        }
    }

    protected function seedDemoDocuments(): void
    {
        // Create sample folders/files in the file manager
        if (!class_exists(\Modules\Core\Models\FileManagerFolder::class)) {
            return;
        }

        $folders = [
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Demo Documents',
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Demo Reports',
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($folders as $folder) {
            try {
                DB::table('file_manager_folders')->insertOrIgnore($folder);
            } catch (\Exception $e) {
                // Table might not exist
            }
        }
    }
}
