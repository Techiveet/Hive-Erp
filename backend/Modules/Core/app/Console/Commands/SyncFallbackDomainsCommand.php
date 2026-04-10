<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantDomainService;

class SyncFallbackDomainsCommand extends Command
{
    protected $signature = 'hive:sync-fallback-domains
        {--dry-run : Preview fallback tenant domain changes without saving them}';

    protected $description = 'Align generated fallback tenant domains with the current ROOT_DOMAIN so domain or VPS moves are easier to redeploy.';

    public function __construct(
        protected TenantDomainService $tenantDomains,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $rootDomain = $this->tenantDomains->currentRootDomain();
        $dryRun = (bool) $this->option('dry-run');
        $tenants = Tenant::query()->with('domains')->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->components->info('No tenants found. Nothing to sync.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '%s fallback tenant domains for root domain [%s].',
            $dryRun ? 'Previewing' : 'Syncing',
            $rootDomain
        ));

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $conflicts = 0;

        foreach ($tenants as $tenant) {
            $expectedDomain = $this->tenantDomains->expectedFallbackDomain($tenant);
            $fallbackDomain = $tenant->fallbackDomain();

            if ($dryRun) {
                if (! $fallbackDomain) {
                    $created++;
                    $this->line("[create] {$tenant->id}: {$expectedDomain}");
                    continue;
                }

                if ($fallbackDomain->domain !== $expectedDomain) {
                    $updated++;
                    $this->line("[update] {$tenant->id}: {$fallbackDomain->domain} -> {$expectedDomain}");
                    continue;
                }

                $unchanged++;
                $this->line("[ok] {$tenant->id}: {$expectedDomain}");
                continue;
            }

            try {
                $result = $this->tenantDomains->syncFallbackDomain($tenant);
                $domain = $result['domain'];

                if ($result['status'] === 'created') {
                    $created++;
                    $this->line("[create] {$tenant->id}: {$domain->domain}");
                    continue;
                }

                if ($result['status'] === 'updated') {
                    $updated++;
                    $previous = (string) ($result['previous_domain'] ?? 'unknown');
                    $this->line("[update] {$tenant->id}: {$previous} -> {$domain->domain}");
                    continue;
                }

                $unchanged++;
                $this->line("[ok] {$tenant->id}: {$domain->domain}");
            } catch (ValidationException $exception) {
                $conflicts++;
                $message = $exception->errors()['domain'][0] ?? $exception->getMessage();
                $this->components->warn("Skipped {$tenant->id}: {$message}");
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Unchanged', $unchanged],
                ['Skipped', $conflicts],
            ]
        );

        if ($conflicts > 0) {
            $this->components->warn('Some fallback domains were skipped because the expected hostname is already attached elsewhere.');
        } else {
            $this->components->info($dryRun ? 'Dry run completed.' : 'Fallback tenant domains are aligned with the current platform root domain.');
        }

        return self::SUCCESS;
    }
}
