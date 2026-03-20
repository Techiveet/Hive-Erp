<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tenancy\Models\Tenant;
use Modules\Identity\Models\User;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\Permission;
use Modules\Core\Models\Activity;
use Modules\Core\Models\ActivityArchive;
use Modules\Core\Models\Language;
use Modules\Core\Models\Translation;
use Meilisearch\Client;

class ScoutTenantImport extends Command
{
    protected $signature = 'scout:import-all {--flush : Flush all Meilisearch indexes before importing}';
    protected $description = 'Safely import Central and Tenant data and configure Meilisearch settings';

    public function handle()
    {
        $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));

        if ($this->option('flush')) {
            $this->warn('Flushing all Meilisearch indexes...');
            try {
                foreach ($client->getIndexes() as $index) {
                    $client->deleteIndex($index->getUid());
                }
                $this->info('All indexes flushed successfully.');
            } catch (\Exception $e) {
                $this->error('Failed to flush: ' . $e->getMessage());
            }
        }

        // 🚀 CRITICAL FIX: Force Scout to process synchronously.
        config(['scout.queue' => false]);
        config(['queue.default' => 'sync']);

        // 1. Configure and Import Central Data
        $this->info('Configuring & Indexing Central Database...');

        $this->configureIndex($client, 'central_users', 'users');
        $this->configureIndex($client, 'central_roles', 'roles');
        $this->configureIndex($client, 'central_permissions', 'permissions');
        $this->configureIndex($client, 'central_languages', 'languages');
        $this->configureIndex($client, 'central_translations', 'translations');

        Tenant::makeAllSearchable();
        User::makeAllSearchable();
        Role::makeAllSearchable();
        Permission::makeAllSearchable();
        Language::makeAllSearchable();
        Translation::makeAllSearchable();

        // 🚀 Route Audit Logs
        $this->info('Routing Audit Logs...');
        $activityTenants = Activity::select('tenant_id')->distinct()->pluck('tenant_id');
        foreach ($activityTenants as $tid) {
            Activity::where('tenant_id', $tid)->searchable();
        }
        $archiveTenants = ActivityArchive::select('tenant_id')->distinct()->pluck('tenant_id');
        foreach ($archiveTenants as $tid) {
            ActivityArchive::where('tenant_id', $tid)->searchable();
        }

        // 2. Configure and Import Tenant-Specific Data
        $this->info('Starting Tenant Indexing...');

        Tenant::all()->each(function ($tenant) use ($client) {
            $this->info("Switching to Tenant: {$tenant->id}");

            // 🚀 Apply Meilisearch Configuration for Tenant
            $this->configureIndex($client, "tenant_{$tenant->id}_users", 'users');
            $this->configureIndex($client, "tenant_{$tenant->id}_roles", 'roles');
            $this->configureIndex($client, "tenant_{$tenant->id}_permissions", 'permissions');
            $this->configureIndex($client, "tenant_{$tenant->id}_languages", 'languages');
            $this->configureIndex($client, "tenant_{$tenant->id}_translations", 'translations');

            // Initialize Tenancy
            tenancy()->initialize($tenant);

            User::makeAllSearchable();
            Role::makeAllSearchable();
            Permission::makeAllSearchable();
            Language::makeAllSearchable();
            Translation::makeAllSearchable();

            tenancy()->end();
        });

        $this->info('🚀 All nodes indexed and configured perfectly!');
    }

    /**
     * 🚀 Instructs Meilisearch on exactly which columns to search and filter
     */
    private function configureIndex(Client $client, string $indexName, string $type)
    {
        try {
            $index = $client->index($indexName);

            if ($type === 'roles') {
                $index->updateSearchableAttributes(['name', 'permissions']);
                $index->updateFilterableAttributes(['id', 'guard_name', 'tenant_id']);
            }
            elseif ($type === 'users') {
                $index->updateSearchableAttributes(['name', 'email']);
                $index->updateFilterableAttributes(['id', 'guard_name', 'tenant_id', 'is_active', 'roles']);
            }
            elseif ($type === 'permissions') {
                $index->updateSearchableAttributes(['name']);
                $index->updateFilterableAttributes(['id', 'guard_name', 'tenant_id']);
            }
            elseif ($type === 'languages') {
                $index->updateSearchableAttributes(['name', 'code']);
                $index->updateFilterableAttributes(['id', 'tenant_id', 'is_default']);
            }
            elseif ($type === 'translations') {
                // 🚀 Removed 'group' from search
                $index->updateSearchableAttributes(['key', 'value']);

                // 🚀 Swapped to 'language_id' and removed 'group'
                $index->updateFilterableAttributes(['id', 'tenant_id', 'language_id']);
            }

            $this->line("   ✓ Configured settings for index: {$indexName}");
        } catch (\Exception $e) {
            $this->error("   ✗ Failed to configure {$indexName}: " . $e->getMessage());
        }
    }
}
