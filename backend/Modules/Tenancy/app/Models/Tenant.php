<?php

namespace Modules\Tenancy\Models;

use Illuminate\Support\Collection;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Laravel\Scout\Searchable;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    // 🚀 FIX: Removed 'LogsActivity'. We handle logging manually in the Controller.
    use HasDatabase, HasDomains, Searchable;

    protected $fillable = [
        'id',
        'name',
        'plan',
        'data',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'plan',
        ];
    }

    // 🚀 CRITICAL FIX 1: Lock the index name!
    // Tenants belong to the central ecosystem. They should NOT have dynamic prefixes.
    public function searchableAs()
    {
        return 'central_tenants';
    }

    // 🚀 CRITICAL FIX 2: Meilisearch Primary Key Sanitization!
    // Meilisearch strictly rejects IDs with dots (.) or spaces. We must format it for Scout.
    public function getScoutKey()
    {
        return str_replace(['.', ' '], '-', $this->getKey());
    }

   public function toSearchableArray(): array
    {
        // 🚀 THE FIX: Explicitly eager load the domains relationship
        // to satisfy Laravel's strict lazy loading prevention!
        $this->loadMissing('domains');

        return [
            'id'         => $this->id,
            'name'       => $this->name ?? $this->id,
            'plan'       => $this->plan ?? 'Standard',
            'domain'     => $this->primaryDomain()?->domain,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->id !== null;
    }

    public function orderedDomains(): Collection
    {
        $this->loadMissing('domains');

        return $this->domains
            ->sortBy([
                fn (Domain $domain) => $domain->is_primary ? 0 : 1,
                fn (Domain $domain) => $domain->is_fallback ? 0 : 1,
                fn (Domain $domain) => $domain->domain,
            ])
            ->values();
    }

    public function primaryDomain(): ?Domain
    {
        return $this->orderedDomains()->first();
    }

    public function fallbackDomain(): ?Domain
    {
        $this->loadMissing('domains');

        return $this->domains->first(fn (Domain $domain) => (bool) $domain->is_fallback);
    }
}
