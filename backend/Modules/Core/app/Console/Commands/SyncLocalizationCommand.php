<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\Language;
use Modules\Core\Models\Translation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SyncLocalizationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'localization:sync {--force-base : Force tenants to sync from the central base /lang folder}';

    /**
     * The console command description.
     */
    protected $description = 'Sync categorized JSON translation files from language folders into the database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isTenant = tenant('id') !== null;
        $context = $isTenant ? "Tenant (" . tenant('id') . ")" : "Central Command";

        $this->info("🌍 Starting Localization Matrix Sync for: <options=bold>{$context}</>");

        // 1. Resolve Pathing
        $tenantPath = storage_path('app/lang');
        $basePath = base_path('lang');

        // Smart Fallback: Use Tenant folder if it exists and has content, otherwise use Central Base
        $langPath = ($isTenant && !$this->option('force-base') && File::isDirectory($tenantPath) && count(File::directories($tenantPath)) > 0)
                    ? $tenantPath
                    : $basePath;

        if (!File::isDirectory($langPath)) {
            $this->error("❌ No lang directory found at {$langPath}");
            return;
        }

        // 2. Scan for Language Folders (e.g., /en, /am)
        $directories = File::directories($langPath);
        $importedCount = 0;

        foreach ($directories as $dir) {
            $code = basename($dir); // Extracts 'en' or 'am'
            $name = $code === 'en' ? 'English' : strtoupper($code);

            // Ensure the language exists in the DB
            $language = Language::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true, 'is_default' => $code === 'en']
            );

            // 3. Scan JSON files inside the language folder
            $files = File::files($dir);
            $this->info("⏳ Syncing '{$name}' ({$code}) from physical files...");

            $totalKeysFound = 0;

            foreach ($files as $file) {
                if ($file->getExtension() === 'json') {
                    $group = $file->getFilenameWithoutExtension(); // e.g., 'auth'
                    $data = json_decode(File::get($file->getPathname()), true);

                    if (is_array($data) && count($data) > 0) {
                        $totalKeysFound += count($data);

                        DB::beginTransaction();
                        try {
                            foreach ($data as $subKey => $value) {
                                // Smart Prefixing: If the file is global.json, don't prefix. Otherwise, prefix with filename.
                                $fullKey = $group === 'global' ? $subKey : "{$group}.{$subKey}";

                                Translation::updateOrCreate(
                                    ['language_id' => $language->id, 'key' => $fullKey],
                                    ['value' => $value]
                                );
                            }
                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            $this->error("\n❌ Failed to sync file {$group}.json: " . $e->getMessage());
                        }
                    }
                }
            }

            $this->line("   ↳ Synced {$totalKeysFound} keys across " . count($files) . " files.");

            // 4. Clear the isolated cache
            $cachePrefix = $isTenant ? 'tenant_' . tenant('id') : 'central';
            Cache::forget("{$cachePrefix}_translations_{$code}");

            $importedCount++;
        }

        $this->newLine();
        $this->info("✅ Successfully compiled {$importedCount} language folders into the database!");
    }
}
