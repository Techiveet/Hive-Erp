<?php

namespace Modules\Tenancy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantDomainService;
use Modules\Tenancy\Support\TenantLandingTemplateCatalog;
use Modules\Tenancy\Support\TenantProvisioningService;

class ProvisionTenantCommand extends Command
{
    protected $signature = 'hive:provision-tenant
        {id : Tenant ID}
        {name : Tenant display name}
        {--plan=business : Subscription plan key}
        {--domain= : Fallback or custom tenant domain}
        {--business-type=general : Business type key for the landing page}
        {--admin-name=Tenant Owner : Primary tenant administrator name}
        {--admin-email= : Primary tenant administrator email}
        {--admin-password= : Primary tenant administrator password}
        {--module=* : Repeatable module slug to enable for the tenant}
        {--custom-module=* : Repeatable custom module in name[:category[:description]] format}
        {--force : Run without the confirmation prompt}';

    protected $description = 'Provision a tenant from the server for client-owned or premium deployments with selected modules.';

    public function handle(
        TenantProvisioningService $provisioning,
        TenantDomainService $domains,
        TenantLandingTemplateCatalog $landingTemplates,
    ): int {
        $tenantId = strtolower(trim((string) $this->argument('id')));
        $tenantName = trim((string) $this->argument('name'));
        $plan = strtolower(trim((string) $this->option('plan')));
        $businessType = $landingTemplates->normalizeBusinessType((string) $this->option('business-type'));
        $adminName = trim((string) $this->option('admin-name'));
        $adminEmail = strtolower(trim((string) ($this->option('admin-email') ?: "admin@{$tenantId}.local")));
        $adminPassword = (string) ($this->option('admin-password') ?: Str::random(20));
        $domain = trim((string) ($this->option('domain') ?: $domains->expectedFallbackDomain($tenantId)));
        $requestedModules = TenantModuleCatalog::normalizeRequestedModules($this->option('module'));
        $customModules = $this->parseCustomModules($this->option('custom-module'));

        if (! preg_match('/^[a-z0-9][a-z0-9-]{0,19}$/', $tenantId)) {
            $this->components->error('Tenant ID must be lowercase alpha-numeric with optional hyphens and up to 20 characters.');

            return self::FAILURE;
        }

        if ($tenantName === '') {
            $this->components->error('Tenant name cannot be empty.');

            return self::FAILURE;
        }

        if (! array_key_exists($plan, TenantModuleCatalog::planPricing())) {
            $this->components->error('Unknown plan. Use one of: ' . implode(', ', array_keys(TenantModuleCatalog::planPricing())));

            return self::FAILURE;
        }

        if (Tenant::query()->where('id', $tenantId)->exists()) {
            $this->components->error("Tenant [{$tenantId}] already exists.");

            return self::FAILURE;
        }

        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('Provide a valid admin email address.');

            return self::FAILURE;
        }

        if (strlen($adminPassword) < 8) {
            $this->components->error('Admin password must be at least 8 characters.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->components->twoColumnDetail('Tenant', "{$tenantName} ({$tenantId})");
            $this->components->twoColumnDetail('Plan', $plan);
            $this->components->twoColumnDetail('Business type', $businessType);
            $this->components->twoColumnDetail('Domain', $domain);
            $this->components->twoColumnDetail('Admin', "{$adminName} <{$adminEmail}>");
            $this->components->twoColumnDetail('Modules', $requestedModules === [] ? 'Plan defaults' : implode(', ', $requestedModules));

            if (! $this->confirm('Provision this tenant now?')) {
                $this->components->warn('Provisioning cancelled.');

                return self::SUCCESS;
            }
        }

        $payload = [
            'id' => $tenantId,
            'name' => $tenantName,
            'plan' => $plan,
            'business_type' => $businessType,
            'landing_page_template' => $landingTemplates->defaultTemplate($businessType),
            'domain' => $domain,
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'admin_password' => $adminPassword,
        ];

        if ($requestedModules !== [] || $customModules !== []) {
            $payload['module_subscriptions'] = [
                'enabled_modules' => $requestedModules,
                'custom_modules' => $customModules,
            ];
        }

        try {
            $tenant = $provisioning->provision($payload, 'cli:' . $adminEmail);
        } catch (\Throwable $exception) {
            $this->components->error('Provisioning failed: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Tenant [{$tenant->id}] provisioned successfully.");
        $this->line("Primary domain: {$tenant->primaryDomain()?->domain}");
        $this->line("Admin email: {$adminEmail}");
        $this->line("Admin password: {$adminPassword}");
        $this->line('Business type: ' . $businessType);
        $this->line('Modules: ' . ($requestedModules === [] ? 'Plan defaults' : implode(', ', $requestedModules)));

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $definitions
     * @return array<int, array{name: string, category: string, description: string}>
     */
    protected function parseCustomModules(array $definitions): array
    {
        return collect($definitions)
            ->map(function ($definition) {
                $parts = array_map('trim', explode(':', (string) $definition, 3));
                $name = $parts[0] ?? '';

                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'category' => $parts[1] ?? 'Custom',
                    'description' => $parts[2] ?? '',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
