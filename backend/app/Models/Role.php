<?php

namespace App\Models;

use Laravel\Scout\Searchable;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends SpatieRole
{
    use HasFactory, Searchable;

    protected $fillable = [
        'name',
        'guard_name',
    ];

    public function toSearchableArray(): array
    {
        $this->loadMissing('permissions');

        return [
            'id'          => (int) $this->id,
            'tenant_id'   => function_exists('tenant') && tenant('id') ? tenant('id') : 'central',
            'name'        => $this->name,
            'guard_name'  => $this->guard_name,
            'permissions' => $this->permissions->pluck('name')->toArray(),
        ];
    }

    protected function makeAllSearchableUsing($query)
    {
        return $query->with('permissions');
    }

    public function isProtected(): bool
    {
        return in_array($this->name, ['Super Admin', 'Admin']);
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