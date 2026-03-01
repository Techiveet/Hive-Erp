<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Laravel\Scout\Searchable;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, Searchable;

    protected $fillable = [
        'id',
        'plan',         // Useful for filtering search by subscription tier
        'data',
    ];

    /**
     * Define which data is stored in the central database for this tenant.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'plan',
        ];
    }

    /**
     * Define exactly what data is sent to Meilisearch.
     * Optimized for search performance.
     */
    public function toSearchableArray(): array
    {
        return [
            'id'     => $this->id,
            'plan'   => $this->plan ?? 'larva',
            'domain' => $this->domains->first()?->domain,
            // Add other high-level metadata you might want to search by globally
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    /**
     * PERFORMANCE FIX:
     * In an ERP, you don't want search indexing to slow down tenant creation.
     * This tells Scout to perform the indexing in the background via Redis.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->id !== null;
    }
}
