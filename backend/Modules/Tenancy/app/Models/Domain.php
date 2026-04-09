<?php

namespace Modules\Tenancy\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

class Domain extends BaseDomain
{
    protected $fillable = [
        'domain',
        'tenant_id',
        'is_primary',
        'is_fallback',
        'verification_status',
        'verification_token',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_fallback' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}
