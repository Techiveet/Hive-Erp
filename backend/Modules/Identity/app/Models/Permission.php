<?php

namespace Modules\Identity\Models;

use Laravel\Scout\Searchable;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Permission extends SpatiePermission
{
    use Searchable, LogsActivity;

    public function toSearchableArray(): array
    {
        return [
            'id'         => (int) $this->id,
            'tenant_id'  => function_exists('tenant') && tenant('id') ? tenant('id') : 'central',
            'name'       => $this->name,
            'guard_name' => $this->guard_name,
        ];
    }

    public function getScoutKey(): mixed
    {
        $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
        return $tenantId . '_' . $this->getKey();
    }

    // 🚀 INJECTED LOGGING RULES
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'guard_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Roles & Permissions');
    }
}
