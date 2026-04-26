<?php

namespace App\Console\Commands\Inventory;

use Illuminate\Console\Command;

class CatalogSyncCommand extends Command
{
    protected $signature = 'catalog:sync {tenant_id?}';
    protected $description = 'Synchronize inventory items with the central product catalog';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        
        if (!$tenantId) {
            $this->error('Please provide a tenant_id as an argument.');
            return 1;
        }

        $items = \Modules\Inventory\Models\InventoryItem::all();
        $this->info("Found {$items->count()} inventory items. Synchronizing...");

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        foreach ($items as $item) {
            // Assign tenant_id if missing from migration
            if (is_null($item->tenant_id)) {
                $item->tenant_id = $tenantId;
                $item->saveQuietly();
            }

            try {
                $item->syncWithCatalog();
            } catch (\Exception $e) {
                $this->error("\nFailed to sync item {$item->id} ({$item->sku}): " . $e->getMessage());
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nSynchronization complete.");

        return 0;
    }
}
