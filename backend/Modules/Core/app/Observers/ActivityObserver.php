<?php

namespace Modules\Core\Observers;

use Modules\Core\Models\Activity;
use Modules\Core\Events\DashboardActivityLogged;

class ActivityObserver
{
    public function created(Activity $activity): void
    {
        // Format payload to perfectly match the Next.js UI expectations
        $payload = [
            'id'          => $activity->id,
            'event'       => $activity->event,
            'description' => $activity->description,
            // Tell the frontend what entity this is (User, Role, Tenant, etc.)
            'subject_type'=> $activity->subject_type ? class_basename($activity->subject_type) : '',
            'causer'      => $activity->causer ? $activity->causer->name : ($activity->properties['causer_name'] ?? 'SYSTEM'),
            'time'        => 'Just now',
        ];

        $tenantId = $activity->tenant_id ?? 'central';

        // 1. Broadcast the real-time event to the Next.js frontend
        event(new DashboardActivityLogged($payload, $tenantId));

        // 2. Clear the cache so the next page refresh has accurate numbers
        \Illuminate\Support\Facades\Cache::forget("dashboard_stats_{$tenantId}");
    }
}
