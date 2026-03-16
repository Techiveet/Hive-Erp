<?php

namespace App\Models;

use Laravel\Scout\Searchable;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Role extends SpatieRole
{
    use HasFactory, Searchable, LogsActivity;

    protected $fillable = [
        'name',
        'guard_name',
    ];

    public function searchableAs()
    {
        $prefix = function_exists('tenant') && tenant('id')
            ? 'tenant_' . tenant('id') . '_'
            : 'central_';

        return $prefix . $this->getTable();
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing('permissions');

        return [
            'id'          => (int) $this->id,
            'tenant_id'   => function_exists('tenant') && tenant('id') ? tenant('id') : 'central',
            'name'        => $this->name,
            'guard_name'  => $this->guard_name,
            // 🚀 Meilisearch will index this array, allowing search by permission name
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'guard_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Roles & Permissions');
    }
}
