<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Language;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Modules\Core\Console\Commands\SyncLocalizationCommand;
use Modules\Core\Support\Localization\BaseDictionary;
use Modules\Core\Support\Localization\AuthDictionary;
use Modules\Core\Support\Localization\LandingDictionary;
use Modules\Core\Support\Localization\DashboardDictionary;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $isTenant = function_exists('tenant') && tenant('id');
        $context = $isTenant ? "Tenant (" . tenant('id') . ")" : "Central Base";

        $this->command->info("\n🌍 Initializing Localization for: {$context}");

        $this->bootstrapPhysicalFiles();

        $coreLanguages = [
            ['name' => 'English', 'code' => 'en', 'is_active' => true, 'is_default' => true],
            ['name' => 'Amharic', 'code' => 'am', 'is_active' => true, 'is_default' => false]
        ];

        foreach ($coreLanguages as $langData) {
            Language::updateOrCreate(['code' => $langData['code']], $langData);
        }

        $commands = Artisan::all();
        if (!isset($commands['localization:sync'])) {
            $this->command->warn("! [NOTE] Manually registering sync command for this process...");
            app(\Illuminate\Contracts\Console\Kernel::class)->registerCommand(app(SyncLocalizationCommand::class));
        }

        $this->command->info("⏳ Triggering localization sync...");

        Artisan::call('localization:sync');

        $this->command->line(Artisan::output());
        $this->command->info("✅ Localization matrix initialized and synced.");
    }

    protected function bootstrapPhysicalFiles(): void
    {
        $isTenant = function_exists('tenant') && tenant('id');
        $langPath = $isTenant ? storage_path('app/lang') : base_path('lang');

        $locales = ['en', 'am'];
        $dictionaries = [
            BaseDictionary::class,
            AuthDictionary::class,
            LandingDictionary::class,
            DashboardDictionary::class,
        ];

        // 1. Build the merged matrix
        $defaultMatrix = [];
        foreach ($locales as $locale) {
            $defaultMatrix[$locale] = [];
            foreach ($dictionaries as $dictionary) {
                // We use array_replace_recursive so that keys in later dictionaries 
                // override earlier ones, while merging nested groups (like 'global')
                $defaultMatrix[$locale] = array_replace_recursive(
                    $defaultMatrix[$locale],
                    $dictionary::getTranslations($locale)
                );
            }
        }

        // 2. Write physical files
        foreach ($defaultMatrix as $locale => $groups) {
            $path = "{$langPath}/{$locale}";
            if (!File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
            }

            foreach ($groups as $group => $translations) {
                // 🚀 Force overwrite the files during seeding so new keys are always caught!
                File::put("{$path}/{$group}.json", json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }
}
