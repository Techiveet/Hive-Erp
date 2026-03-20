<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class ActivityArchive extends Model
{
    use Searchable;

    // 🚀 THE FIX: Hardcode the connection for the Archiver too.
    protected $connection = 'central';

    protected $table = 'activity_log_archives';

    protected $guarded = [];

    protected $casts = [
        'properties' => 'collection',
    ];

    public function causer()
    {
        return $this->morphTo();
    }

    // 🚀 DYNAMIC ROUTING: Exactly the same as the live Activity model
    public function searchableAs()
    {
        $tenantId = $this->tenant_id ?? 'central';

        $prefix = $tenantId === 'central'
            ? 'central_'
            : 'tenant_' . $tenantId . '_';

        return $prefix . $this->getTable();
    }

    // 🚀 FORMAT DATA: Keep searches consistent across live and archived logs
    public function toSearchableArray(): array
    {
        $properties = $this->properties ? $this->properties->toArray() : [];

        return [
            'id'          => (int) $this->id,
            'log_name'    => $this->log_name,
            'description' => $this->description,
            'causer_name' => $properties['causer_name'] ?? 'System',
            'tenant_id'   => $this->tenant_id ?? 'central',
            'created_at'  => $this->created_at?->timestamp,
        ];
    }
}
