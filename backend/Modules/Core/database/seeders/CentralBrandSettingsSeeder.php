<?php

namespace Modules\Core\Database\Seeders;

class CentralBrandSettingsSeeder extends BaseSettingsSeeder
{
    public function run(): void
    {
        $this->seedSettings([
            'logo_light' => null,
            'logo_dark' => null,
            'favicon' => null,
            'sidebar_icon' => null,
            'app_title' => 'HIVE.OS',
            'footer_text' => 'Powered by HIVE.OS',
            'primary_color' => '#10b9ff',
            'auth_background_image' => null,
            'auth_welcome_message' => 'Sign in to access your secure control hub.',
            'font_family' => 'Inter',
            'meta_description' => null,
            'og_image' => null,
            'hide_watermark' => false,
            'document_header_color' => '#1e293b',
            'company_tax_id' => null,
            'pdf_logo' => null,
        ]);

        $this->clearBrandSettingsCache();

        if ($this->command) {
            $this->command->info('Central brand settings seeded.');
        }
    }
}
