<?php

namespace Modules\Tenancy\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Tenancy\Models\Domain;
use Modules\Tenancy\Models\Tenant;

class TenantDomainService
{
    public function currentRootDomain(): string
    {
        $configuredRoot = $this->normalizeDomain((string) env('ROOT_DOMAIN', ''));

        if ($configuredRoot !== '') {
            return $configuredRoot;
        }

        $frontendUrl = (string) (config('app.frontend_url') ?: env('FRONTEND_URL') ?: config('app.url', 'http://localhost:3000'));
        $host = parse_url($frontendUrl, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            $normalizedHost = $this->normalizeDomain($host);

            if (Str::startsWith($normalizedHost, 'hive.')) {
                return Str::after($normalizedHost, 'hive.');
            }

            return $normalizedHost;
        }

        return 'localhost';
    }

    public function expectedFallbackDomain(Tenant|string $tenant): string
    {
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : trim((string) $tenant);
        $rootDomain = $this->currentRootDomain();

        return $rootDomain === 'localhost'
            ? "{$tenantId}.localhost"
            : "{$tenantId}.{$rootDomain}";
    }

    public function normalizeDomain(string $domain): string
    {
        $normalized = trim(Str::lower($domain));
        $normalized = preg_replace('#^https?://#', '', $normalized) ?? $normalized;
        $normalized = preg_replace('#/.*$#', '', $normalized) ?? $normalized;
        $normalized = preg_replace('#:\d+$#', '', $normalized) ?? $normalized;
        $normalized = trim($normalized, '.');

        if ($normalized === '') {
            return '';
        }

        if (function_exists('idn_to_ascii')) {
            try {
                $ascii = idn_to_ascii($normalized, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            } catch (\ValueError) {
                return '';
            }

            if (is_string($ascii) && $ascii !== '') {
                $normalized = Str::lower($ascii);
            }
        }

        return $normalized;
    }

    public function createFallbackDomain(Tenant $tenant, string $domain): Domain
    {
        return $tenant->domains()->create([
            'domain' => $this->normalizeDomain($domain),
            'is_primary' => true,
            'is_fallback' => true,
            'verification_status' => 'verified',
            'verification_token' => null,
            'verified_at' => now(),
        ]);
    }

    public function syncFallbackDomain(Tenant $tenant): array
    {
        $tenant->loadMissing('domains');

        $expectedDomain = $this->expectedFallbackDomain($tenant);
        $fallbackDomain = $this->fallbackDomain($tenant);

        if ($fallbackDomain && $fallbackDomain->domain === $expectedDomain) {
            return [
                'status' => 'unchanged',
                'domain' => $fallbackDomain->refresh(),
            ];
        }

        $this->assertDomainAvailable($expectedDomain, $fallbackDomain?->id);

        if ($fallbackDomain) {
            $previousDomain = $fallbackDomain->domain;

            $fallbackDomain->forceFill([
                'domain' => $expectedDomain,
                'verification_status' => 'verified',
                'verification_token' => null,
                'verified_at' => now(),
            ])->save();

            return [
                'status' => 'updated',
                'domain' => $fallbackDomain->refresh(),
                'previous_domain' => $previousDomain,
            ];
        }

        $primaryDomain = $this->primaryDomain($tenant);

        $created = $tenant->domains()->create([
            'domain' => $expectedDomain,
            'is_primary' => $primaryDomain === null,
            'is_fallback' => true,
            'verification_status' => 'verified',
            'verification_token' => null,
            'verified_at' => now(),
        ]);

        return [
            'status' => 'created',
            'domain' => $created->refresh(),
        ];
    }

    public function createCustomDomain(Tenant $tenant, string $domain): Domain
    {
        $normalized = $this->normalizeCustomDomain($domain);
        $this->assertDomainAvailable($normalized);

        return $tenant->domains()->create([
            'domain' => $normalized,
            'is_primary' => false,
            'is_fallback' => false,
            'verification_status' => 'pending',
            'verification_token' => $this->generateVerificationToken(),
            'verified_at' => null,
        ]);
    }

    public function updateCustomDomain(Tenant $tenant, Domain $domain, string $newDomain): Domain
    {
        $this->assertBelongsToTenant($tenant, $domain);

        if ($domain->is_fallback) {
            throw ValidationException::withMessages([
                'domain' => ['The generated fallback domain cannot be edited.'],
            ]);
        }

        $normalized = $this->normalizeCustomDomain($newDomain);
        $this->assertDomainAvailable($normalized, $domain->id);

        $domain->fill([
            'domain' => $normalized,
            'verification_status' => 'pending',
            'verification_token' => $this->generateVerificationToken(),
            'verified_at' => null,
        ]);
        $domain->save();

        return $domain->refresh();
    }

    public function verifyDomain(Tenant $tenant, Domain $domain): array
    {
        $this->assertBelongsToTenant($tenant, $domain);

        if ($domain->verification_status === 'verified') {
            return [
                'verified' => true,
                'domain' => $domain->fresh(),
            ];
        }

        $recordName = $this->verificationRecordName($domain->domain);
        $records = dns_get_record($recordName, DNS_TXT) ?: [];

        $matched = collect($records)->contains(function (array $record) use ($domain) {
            $values = array_filter([
                $record['txt'] ?? null,
                $record['entries'][0] ?? null,
            ]);

            return collect($values)->contains(
                fn ($value) => is_string($value) && trim($value) === $domain->verification_token
            );
        });

        if (!$matched) {
            return [
                'verified' => false,
                'domain' => $domain->fresh(),
            ];
        }

        $domain->forceFill([
            'verification_status' => 'verified',
            'verified_at' => now(),
        ])->save();

        return [
            'verified' => true,
            'domain' => $domain->fresh(),
        ];
    }

    public function makePrimary(Tenant $tenant, Domain $domain): Domain
    {
        $this->assertBelongsToTenant($tenant, $domain);

        if ($domain->verification_status !== 'verified') {
            throw ValidationException::withMessages([
                'domain' => ['Only verified domains can become the primary tenant address.'],
            ]);
        }

        $tenant->domains()->update(['is_primary' => false]);

        $domain->forceFill(['is_primary' => true])->save();

        return $domain->refresh();
    }

    public function deleteDomain(Tenant $tenant, Domain $domain): void
    {
        $this->assertBelongsToTenant($tenant, $domain);

        if ($domain->is_fallback) {
            throw ValidationException::withMessages([
                'domain' => ['The generated fallback domain cannot be removed.'],
            ]);
        }

        if ($domain->is_primary) {
            throw ValidationException::withMessages([
                'domain' => ['Make another verified domain primary before removing this one.'],
            ]);
        }

        $domain->delete();
    }

    public function domainsPayload(Tenant $tenant): array
    {
        $tenant->loadMissing('domains');

        return $this->orderedDomains($tenant)
            ->map(fn (Domain $domain) => $this->domainPayload($domain))
            ->values()
            ->all();
    }

    public function domainPayload(Domain $domain): array
    {
        return [
            'id' => (int) $domain->id,
            'domain' => $domain->domain,
            'is_primary' => (bool) $domain->is_primary,
            'is_fallback' => (bool) $domain->is_fallback,
            'verification_status' => $domain->verification_status ?: 'pending',
            'verification_token' => $domain->verification_token,
            'verified_at' => optional($domain->verified_at)->toIso8601String(),
            'verification_record_name' => $this->verificationRecordName($domain->domain),
            'verification_record_value' => $domain->verification_token,
            'routing_record_type' => $this->recommendedRoutingRecordType($domain->domain),
            'routing_target' => $this->routingTarget($domain->domain),
            'is_apex' => $this->isApexDomain($domain->domain),
        ];
    }

    public function primaryDomain(Tenant $tenant): ?Domain
    {
        return $this->orderedDomains($tenant)->first();
    }

    public function fallbackDomain(Tenant $tenant): ?Domain
    {
        $tenant->loadMissing('domains');

        return $tenant->domains
            ->first(fn ($domain) => (bool) $domain->is_fallback);
    }

    public function verificationRecordName(string $domain): string
    {
        return '_hive-verification.' . $this->normalizeDomain($domain);
    }

    public function routingTarget(string $domain): string
    {
        if ($this->isApexDomain($domain)) {
            $serverIp = $this->serverIp();

            if ($serverIp !== null) {
                return $serverIp;
            }
        }

        $frontendUrl = (string) (config('app.frontend_url') ?: env('FRONTEND_URL') ?: config('app.url', 'http://localhost:3000'));

        $host = parse_url($frontendUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? Str::lower($host) : 'localhost';
    }

    protected function orderedDomains(Tenant $tenant): Collection
    {
        $tenant->loadMissing('domains');

        return $tenant->domains
            ->sortBy([
                fn (Domain $domain) => $domain->is_primary ? 0 : 1,
                fn (Domain $domain) => $domain->is_fallback ? 0 : 1,
                fn (Domain $domain) => $domain->domain,
            ])
            ->values();
    }

    protected function normalizeCustomDomain(string $domain): string
    {
        $normalized = $this->normalizeDomain($domain);

        if ($normalized === '' || !filter_var('https://' . $normalized, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'domain' => ['Enter a valid hostname such as app.customer.com.'],
            ]);
        }

        if (!str_contains($normalized, '.')) {
            throw ValidationException::withMessages([
                'domain' => ['Custom domains must include a full hostname such as app.customer.com.'],
            ]);
        }

        $centralHosts = collect(config('tenancy.central_domains', []))
            ->map(fn ($host) => $this->normalizeDomain((string) $host))
            ->filter()
            ->all();

        if (in_array($normalized, $centralHosts, true)) {
            throw ValidationException::withMessages([
                'domain' => ['That hostname is reserved for the central control hub.'],
            ]);
        }

        return $normalized;
    }

    protected function isApexDomain(string $domain): bool
    {
        return substr_count($this->normalizeDomain($domain), '.') <= 1;
    }

    protected function recommendedRoutingRecordType(string $domain): string
    {
        return $this->isApexDomain($domain) ? 'ALIAS_OR_A' : 'CNAME';
    }

    protected function serverIp(): ?string
    {
        $serverIp = trim((string) env('SERVER_IP', ''));

        if ($serverIp === '') {
            return null;
        }

        return filter_var($serverIp, FILTER_VALIDATE_IP) ? $serverIp : null;
    }

    protected function assertBelongsToTenant(Tenant $tenant, Domain $domain): void
    {
        if ((string) $domain->tenant_id !== (string) $tenant->id) {
            throw ValidationException::withMessages([
                'domain' => ['That domain does not belong to the selected tenant.'],
            ]);
        }
    }

    protected function assertDomainAvailable(string $domain, ?int $ignoreId = null): void
    {
        $query = Domain::query()->where('domain', $domain);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'domain' => ['That hostname is already attached to another tenant.'],
            ]);
        }
    }

    protected function generateVerificationToken(): string
    {
        return Str::upper(Str::random(32));
    }
}
