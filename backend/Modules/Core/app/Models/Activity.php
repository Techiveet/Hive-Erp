<?php

namespace Modules\Core\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;

class Activity extends SpatieActivity
{
    use Searchable;

    // 🚀 THE FIX: Hardcode the connection. This makes it 100% immune to Tenant Hijacking.
    protected $connection = 'central';

    protected static function booted()
    {
        static::creating(function ($activity) {
            // Assign the correct Tenant ID or default to 'central'
            if (empty($activity->tenant_id)) {
                $activity->tenant_id = (function_exists('tenant') && tenant('id')) ? tenant('id') : 'central';
            }

            $properties = $activity->properties ? $activity->properties->toArray() : [];

            // Permanently stamp the Operator's Name into the WORM record
            if (!isset($properties['causer_name'])) {
                if (auth()->check()) {
                    $properties['causer_name'] = auth()->user()->name;
                } elseif ($activity->causer) {
                    $properties['causer_name'] = $activity->causer->name;
                } else {
                    $properties['causer_name'] = 'System';
                }
            }

            // Generate cryptographic signature
            $payload = $activity->log_name . $activity->description . $activity->causer_id . $activity->tenant_id . $activity->created_at;
            $properties['signature'] = hash_hmac('sha256', $payload, config('app.key'));

            $activity->properties = collect($properties);
        });

        static::updating(function ($activity) {
            Log::critical("SECURITY ALERT: Attempted unauthorized modification of audit log ID: {$activity->id}");
            throw new \Exception('Audit logs are strictly immutable. Update operations are forbidden.');
        });

        static::deleting(function ($activity) {
            Log::critical("SECURITY ALERT: Attempted unauthorized deletion of audit log ID: {$activity->id}");
            throw new \Exception('Audit logs cannot be deleted from the application layer. Use the Archiver Command.');
        });
    }

    // 🚀 DYNAMIC ROUTING: Route directly to the correct index based on the row's data!
    public function searchableAs()
    {
        $tenantId = $this->tenant_id ?? 'central';

        $prefix = $tenantId === 'central'
            ? 'central_'
            : 'tenant_' . $tenantId . '_';

        return $prefix . $this->getTable(); // e.g., "central_activity_log" or "tenant_apple_activity_log"
    }

    // 🚀 FORMAT DATA: Tell Meilisearch exactly what to index
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
