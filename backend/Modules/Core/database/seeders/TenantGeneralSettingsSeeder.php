<?php

namespace Modules\Core\Database\Seeders;

class TenantGeneralSettingsSeeder extends BaseSettingsSeeder
{
    public function run(): void
    {
        $this->seedSettings([
            'support_email' => '',
            'support_phone' => '',
            'system_email_name' => 'HIVE.OS',
            'system_email_address' => 'noreply@hive-os.com',
            'default_timezone' => 'UTC',
            'default_currency' => 'USD',
            'date_format' => 'YYYY-MM-DD',
            'time_format' => '24h',
            'max_upload_size' => 10,
            'max_upload_unit' => 'MB',
            'session_timeout_minutes' => 120,
            'enable_registration' => false,
            'require_2fa' => false,
        ]);

        $this->clearGeneralSettingsCache();

        if ($this->command) {
            $this->command->info('Tenant general settings seeded.');
        }
    }
}
