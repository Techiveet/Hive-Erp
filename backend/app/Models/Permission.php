<?php

namespace App\Models;

use Laravel\Scout\Searchable;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id'         => (int) $this->id,
            'tenant_id'  => function_exists('tenant') && tenant('id') ? tenant('id') : 'central',
            'name'       => $this->name,
            'guard_name' => $this->guard_name,
        ];
    }

    /**
     * Override the default Scout Key to prevent ID collisions
     */
    public function getScoutKey(): mixed
    {
        $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
        return $tenantId . '_' . $this->getKey();
    }
}