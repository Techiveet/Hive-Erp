<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;

class ScoutTenantImport extends Command
{
    protected $signature = 'scout:import-all';
    protected $description = 'Import Central and all Tenant data into Meilisearch';

    public function handle()
    {
        // 1. Import Central Data (Web Guard)
        $this->info('Indexing Central Database...');
        $this->call('scout:import', ['model' => User::class]);
        $this->call('scout:import', ['model' => Role::class]);
        $this->call('scout:import', ['model' => Permission::class]);

        // 2. Import Tenant Data (Tenant Guard)
        $this->info('Starting Tenant Indexing...');
        
        Tenant::all()->each(function ($tenant) {
            $this->info("Switching to Tenant: {$tenant->id}");
            
            // Initialize Tenancy for this tenant
            tenancy()->initialize($tenant);

            // Run imports for this tenant's database
            $this->call('scout:import', ['model' => User::class]);
            $this->call('scout:import', ['model' => Role::class]);
            $this->call('scout:import', ['model' => Permission::class]);

            // Clean up
            tenancy()->end();
        });

        $this->info('All nodes indexed successfully!');
    }
}